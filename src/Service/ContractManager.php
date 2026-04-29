<?php

namespace App\Service;

use App\Bundle\ContractTemplate\Service\TemplateRendererService;
use App\Entity\Contract;
use App\Entity\ContractTemplate;
use App\Entity\ContractTerminationRequest;
use App\Entity\Listing;
use App\Entity\User;
use App\Enum\ContractStatus;
use App\Message\AI\BulkScoreListingsMessage;
use App\Repository\ContractTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

class ContractManager
{
    public function __construct(
        private EntityManagerInterface     $entityManager,
        private ContractTemplateRepository $contractTemplateRepository,
        private string                     $projectDir,
        private InAppNotificationService   $notifier,
        private MessageBusInterface        $bus,
        private LoggerInterface            $logger,
        private TemplateRendererService    $templateRenderer,
    ) {
    }

    /**
     * Generate a new contract for a listing and student.
     * Uses an inline French template matching the PHP version exactly.
     */
    public function generateContract(
        Listing $listing,
        User $student,
        \DateTimeInterface $startDate,
        int $durationMonths,
    ): Contract {
        $contract = new Contract();
        $contract->setContractNumber(Uuid::v4()->toRfc4122());
        $contract->setListing($listing);
        $contract->setStudent($student);
        $contract->setOwner($listing->getOwner());

        $start = \DateTime::createFromInterface($startDate);
        $end   = (clone $start)->modify('+' . $durationMonths . ' months');
        $contract->setStartDate($start);
        $contract->setEndDate($end);

        $monthlyRent = $listing->getPrice();
        $contract->setMonthlyRent((string) $monthlyRent);
        $contract->setSecurityDeposit((string) $monthlyRent);
        $contract->setStatus(ContractStatus::Draft);

        // Render HTML using the bundle's TemplateRendererService (locale-aware, DB-stored template).
        // Falls back to the built-in French body if no DB template exists.
        $html = $this->templateRenderer->render($contract, 'fr');

        // If the owner signed at listing-creation time, embed that signature into
        // the freshly generated contract immediately (no need to wait for the owner
        // to re-sign per contract).
        $ownerSigPath = $listing->getOwnerSignaturePath();
        if ($ownerSigPath) {
            $imgTag = sprintf(
                '<img src="/%s" alt="Owner signature" style="max-height:110px; max-width:100%%;">',
                htmlspecialchars($ownerSigPath, ENT_QUOTES, 'UTF-8')
            );
            $html = str_replace('<!--OWNER_SIGNATURE-->', $imgTag, $html);
            $contract->setOwnerSignaturePath($ownerSigPath);
        }

        $contract->setContractContent($html);
        $contract->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($contract);
        $this->entityManager->flush();

        $this->notifyContractGenerated($contract);

        // Async: re-score listing quality now that it has a new contract
        try {
            $this->bus->dispatch(new BulkScoreListingsMessage([$listing->getId()]));
        } catch (\Throwable $e) {
            $this->logger->warning('Could not dispatch listing score job: ' . $e->getMessage());
        }

        return $contract;
    }

    /**
     * Build the French HTML contract body with placeholders for signatures.
     * Signatures are injected later by signContract() replacing
     * <!--STUDENT_SIGNATURE--> and <!--OWNER_SIGNATURE--> markers.
     */
    private function renderContractHtml(
        Listing $listing,
        User $owner,
        User $student,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        int $durationMonths
    ): string {
        $e = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $type = $listing->getPropertyType();
        $typeStr = $type instanceof \BackedEnum ? $type->value : (string) $type;

        return <<<HTML
<div style="font-family: Georgia, serif; line-height: 1.7; color: #111;">
  <h2 style="text-align:center; margin-bottom:1.5rem;">CONTRAT DE LOCATION RÉSIDENTIELLE</h2>

  <p><strong>ENTRE LES SOUSSIGNÉS :</strong></p>
  <p>
    <strong>Propriétaire :</strong> {$e($owner->getFullName())}<br>
    Email : {$e($owner->getEmail())}<br>
    Ci-après dénommé « le Propriétaire »
  </p>

  <p><strong>ET</strong></p>

  <p>
    <strong>Étudiant :</strong> {$e($student->getFullName())}<br>
    Email : {$e($student->getEmail())}<br>
    Ci-après dénommé « l'Étudiant »
  </p>

  <p><strong>IL A ÉTÉ CONVENU ET ARRÊTÉ CE QUI SUIT :</strong></p>

  <h3>ARTICLE 1 : OBJET DU CONTRAT</h3>
  <p>Le présent contrat a pour objet la location d'un logement à usage de résidence étudiante.</p>

  <h3>ARTICLE 2 : DESCRIPTION DU LOGEMENT</h3>
  <ul>
    <li>Titre : {$e($listing->getTitle())}</li>
    <li>Adresse : {$e($listing->getAddress())}</li>
    <li>Type : {$e($typeStr)}</li>
    <li>Prix mensuel : {$e($listing->getPrice())} TND</li>
    <li>Chambres : {$e($listing->getBedrooms())}</li>
    <li>Salles de bain : {$e($listing->getBathrooms())}</li>
  </ul>

  <h3>ARTICLE 3 : DURÉE DU CONTRAT</h3>
  <p>La présente location est consentie pour une durée de <strong>{$durationMonths} mois</strong>, à compter du <strong>{$start->format('d/m/Y')}</strong> au <strong>{$end->format('d/m/Y')}</strong>.</p>

  <h3>ARTICLE 4 : LOYER ET CHARGES</h3>
  <p>Le loyer mensuel est fixé à <strong>{$e($listing->getPrice())} TND</strong>, payable à la fin de chaque mois. Une commission de 5% est perçue par la plateforme UNIDAR.</p>

  <h3>ARTICLE 5 : OBLIGATIONS DES PARTIES</h3>
  <p><strong>Le Propriétaire s'engage à :</strong></p>
  <ul>
    <li>Mettre le logement en état de jouissance</li>
    <li>Assurer la jouissance paisible du logement</li>
    <li>Effectuer les réparations nécessaires</li>
  </ul>
  <p><strong>L'Étudiant s'engage à :</strong></p>
  <ul>
    <li>Payer le loyer dans les délais convenus</li>
    <li>Occuper le logement conformément à sa destination</li>
    <li>Ne pas céder ou sous-louer le logement</li>
    <li>Rendre le logement en bon état à la fin du contrat</li>
  </ul>

  <h3>ARTICLE 6 : RÉSILIATION</h3>
  <p>Le présent contrat peut être résilié par chaque partie moyennant un préavis de 30 jours.</p>

  <h3>ARTICLE 7 : DROIT APPLICABLE</h3>
  <p>Le présent contrat est soumis au droit tunisien en vigueur.</p>

  <p style="margin-top:2rem;">Fait en deux exemplaires originaux, à Tunis, le {$start->format('d/m/Y')}.</p>

  <table style="width:100%; margin-top:2.5rem; border-collapse:collapse;">
    <tr>
      <td style="width:50%; vertical-align:top; padding:1rem; border:1px solid #e5e7eb;">
        <p style="margin:0 0 .25rem; font-size:.8rem; color:#64748b; text-transform:uppercase; font-weight:700;">Signature du Propriétaire</p>
        <div style="min-height:120px; display:flex; align-items:center; justify-content:center;"><!--OWNER_SIGNATURE--></div>
        <p style="margin:.5rem 0 0; font-weight:600;">{$e($owner->getFullName())}</p>
      </td>
      <td style="width:50%; vertical-align:top; padding:1rem; border:1px solid #e5e7eb;">
        <p style="margin:0 0 .25rem; font-size:.8rem; color:#64748b; text-transform:uppercase; font-weight:700;">Signature de l'Étudiant</p>
        <div style="min-height:120px; display:flex; align-items:center; justify-content:center;"><!--STUDENT_SIGNATURE--></div>
        <p style="margin:.5rem 0 0; font-weight:600;">{$e($student->getFullName())}</p>
      </td>
    </tr>
  </table>
</div>
HTML;
    }

    /**
     * Sign a contract by a user (student or owner).
     */
    public function signContract(Contract $contract, User $user, string $signatureBase64): void
    {
        // Remove data URI prefix if present
        $signatureData = $signatureBase64;
        if (str_contains($signatureData, ',')) {
            $signatureData = substr($signatureData, strpos($signatureData, ',') + 1);
        }

        $decoded = base64_decode($signatureData, true);
        if ($decoded === false) {
            throw new \InvalidArgumentException('Invalid base64 signature data.');
        }

        $signatureDir = $this->projectDir . '/public/uploads/signatures';
        if (!is_dir($signatureDir)) {
            mkdir($signatureDir, 0755, true);
        }

        $isStudent = $contract->getStudent() && $contract->getStudent()->getId() === $user->getId();
        $role = $isStudent ? 'student' : 'owner';
        $filename = sprintf('%s_%d_%d.png', $role, $contract->getId(), time());
        $filepath = $signatureDir . '/' . $filename;

        file_put_contents($filepath, $decoded);

        $relativePath = 'uploads/signatures/' . $filename;

        // Embed the signature image directly inside the contract HTML at its placeholder spot.
        $content = $contract->getContractContent() ?? '';
        $imgTag  = sprintf(
            '<img src="/%s" alt="signature" style="max-height:110px; max-width:100%%;">',
            $relativePath
        );
        $placeholder = $isStudent ? '<!--STUDENT_SIGNATURE-->' : '<!--OWNER_SIGNATURE-->';
        $content = str_replace($placeholder, $imgTag, $content);
        $contract->setContractContent($content);

        if ($isStudent) {
            $contract->setStudentSignaturePath($relativePath);
            // In the student-only signing flow, the student signs first; advance to SignedByStudent.
            // If the owner had already signed, upgrade to SignedByBoth.
            $contract->setStatus(
                $contract->getOwnerSignaturePath()
                    ? ContractStatus::SignedByBoth
                    : ContractStatus::SignedByStudent
            );
        } else {
            $contract->setOwnerSignaturePath($relativePath);
            if ($contract->getStudentSignaturePath()) {
                $contract->setStatus(ContractStatus::SignedByBoth);
            }
        }

        $this->entityManager->flush();

        $this->notifyContractSigned($contract, $user, $isStudent);
    }

    /**
     * Regenerate and persist the full HTML contract body for an existing contract.
     * Use this to fix contracts stored with stub/plain-text content.
     */
    public function regenerateContractContent(Contract $contract): void
    {
        $listing = $contract->getListing();
        $owner   = $contract->getOwner();
        $student = $contract->getStudent();
        $start   = $contract->getStartDate();
        $end     = $contract->getEndDate();

        if (!$listing || !$owner || !$student || !$start || !$end) {
            return;
        }

        $months = (int) $start->diff($end)->m + ($start->diff($end)->y * 12);
        if ($months <= 0) {
            $months = 9;
        }

        $html = $this->renderContractHtml($listing, $owner, $student, $start, $end, $months);

        // Re-embed owner signature
        $ownerSigPath = $contract->getOwnerSignaturePath();
        if ($ownerSigPath) {
            $imgTag = sprintf(
                '<img src="/%s" alt="Owner signature" style="max-height:110px; max-width:100%%;">',
                htmlspecialchars($ownerSigPath, ENT_QUOTES, 'UTF-8')
            );
            $html = str_replace('<!--OWNER_SIGNATURE-->', $imgTag, $html);
        }

        // Re-embed student signature
        $studentSigPath = $contract->getStudentSignaturePath();
        if ($studentSigPath) {
            $imgTag = sprintf(
                '<img src="/%s" alt="Student signature" style="max-height:110px; max-width:100%%;">',
                htmlspecialchars($studentSigPath, ENT_QUOTES, 'UTF-8')
            );
            $html = str_replace('<!--STUDENT_SIGNATURE-->', $imgTag, $html);
        }

        $contract->setContractContent($html);
        $this->entityManager->flush();
    }

    /**
     * Generate and return the contract as a PDF binary string using DomPDF.
     */
    public function downloadContract(Contract $contract): array
    {
        $html = $contract->getContractContent() ?? '';

        // Embed signature images as base64 so DomPDF can render them without
        // needing filesystem access or absolute URL resolution.
        $html = $this->inlineSignatureImages($html);

        // QR encodes structured contract data — works offline, no URL needed
        $qrSvg = $this->generateContractQrSvg($this->buildContractQrData($contract));

        try {
            $options = new Options();
            $options->set('defaultFont', 'serif');
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', false);   // all images are base64 — no HTTP needed
            $options->set('chroot', $this->projectDir . '/public');

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($this->wrapPdfHtml($html, $qrSvg, $contract->getContractNumber() ?? ''), 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            return [
                'content' => $dompdf->output(),
                'type'    => 'application/pdf',
                'ext'     => 'pdf',
            ];
        } catch (\Throwable $e) {
            $this->logger->error('DomPDF generation failed: ' . $e->getMessage());
            return ['content' => $html, 'type' => 'text/html; charset=UTF-8', 'ext' => 'html'];
        }
    }

    /**
     * Replace every <img src="/uploads/..."> in the contract HTML with an
     * inline base64 data URI so the PDF renderer never needs filesystem look-ups.
     */
    private function inlineSignatureImages(string $html): string
    {
        return (string) preg_replace_callback(
            '/<img(\s[^>]*)src="(\/uploads\/[^"]+)"([^>]*)>/i',
            function (array $m) {
                $absolutePath = $this->projectDir . '/public' . $m[2];

                if (!is_file($absolutePath)) {
                    return $m[0]; // file missing — leave tag as-is
                }

                $mime = mime_content_type($absolutePath) ?: 'image/png';
                $b64  = base64_encode((string) file_get_contents($absolutePath));
                $dataUri = "data:{$mime};base64,{$b64}";

                return "<img{$m[1]}src=\"{$dataUri}\"{$m[3]}>";
            },
            $html
        );
    }

    /**
     * Build a compact, human-readable verification block to encode in the QR.
     * Works offline — no URL, no network access needed.
     */
    private function buildContractQrData(Contract $contract): string
    {
        $lines = [
            'UNIDAR - Verification du Contrat',
            '---',
            'Ref : ' . ($contract->getContractNumber() ?? 'N/A'),
            'Etudiant  : ' . ($contract->getStudent()?->getFullName() ?? ''),
            'Email     : ' . ($contract->getStudent()?->getEmail() ?? ''),
            'Proprietaire : ' . ($contract->getOwner()?->getFullName() ?? ''),
            'Bien      : ' . ($contract->getListing()?->getTitle() ?? ''),
            'Loyer     : ' . $contract->getMonthlyRent() . ' TND/mois',
            'Debut     : ' . ($contract->getStartDate()?->format('d/m/Y') ?? ''),
            'Fin       : ' . ($contract->getEndDate()?->format('d/m/Y') ?? ''),
            'Statut    : ' . ($contract->getStatus()?->value ?? ''),
            '---',
            'unidar.app | student.housing.community',
        ];

        return implode("\n", $lines);
    }

    /**
     * Generate a QR code as an inline SVG string.
     * Uses SvgWriter so no GD / Imagick extension is required.
     */
    private function generateContractQrSvg(string $contractNumber): string
    {
        try {
            $result = (new Builder(
                writer: new SvgWriter(),
                data: 'UNIDAR-CONTRACT:' . $contractNumber,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: 120,
                margin: 6,
            ))->build();

            // Strip the XML declaration — DomPDF needs a bare <svg> for inline embedding
            return (string) preg_replace('/<\?xml[^?]*\?>\s*/i', '', $result->getString());
        } catch (\Throwable $e) {
            $this->logger->warning('QR code generation failed: ' . $e->getMessage());
            return ''; // PDF renders fine without it
        }
    }

    private function wrapPdfHtml(string $body, string $qrSvg = '', string $contractNumber = ''): string
    {
        $date     = date('d/m/Y');
        $numLabel = $contractNumber ? htmlspecialchars($contractNumber, ENT_QUOTES, 'UTF-8') : '—';

        // Static UNIDAR shield mark — no animations, pure shapes, renders perfectly in DomPDF.
        // Embedded as base64 <img> so DomPDF never has to parse inline SVG.
        $shieldRaw = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 90" width="44" height="50">'
            . '<path d="M40 3 L72 17 V50 C72 70 58 84 40 90 C22 84 8 70 8 50 V17 Z" fill="#6366f1"/>'
            . '<path d="M40 24 L58 36 V56 H46 V44 H34 V56 H22 V36 Z" fill="#ffffff"/>'
            . '<rect x="34" y="45" width="12" height="11" fill="#fbbf24"/>'
            . '<circle cx="58" cy="29" r="3" fill="#ffffff" fill-opacity="0.8"/>'
            . '</svg>';
        $shieldSvg = '<img src="data:image/svg+xml;base64,' . base64_encode($shieldRaw) . '" '
            . 'style="width:44px; height:50px; display:block;" alt="UNIDAR">';

        // QR code — also embedded as base64 <img> so DomPDF renders it reliably
        // (raw inline <svg> in a table cell is not reliably painted by DomPDF).
        $qrImg = '';
        if ($qrSvg !== '') {
            $qrImg = '<img src="data:image/svg+xml;base64,' . base64_encode($qrSvg) . '" '
                   . 'style="width:110px; height:110px; display:block;" alt="QR Code">';
        }

        // QR verification footer
        $footer = $qrImg ? <<<FOOTER
<div style="margin-top:2.5em; border-top:2px solid #e0e7ff; padding-top:1em;">
  <table style="width:100%; border-collapse:collapse;">
    <tr>
      <td style="border:none; width:120px; padding:0; vertical-align:middle;">{$qrImg}</td>
      <td style="border:none; padding:0 0 0 1.2em; vertical-align:middle;">
        <p style="margin:0 0 .3em; font-size:8.5pt; font-weight:700; color:#4f46e5; text-transform:uppercase; letter-spacing:.06em;">✓ Document UNIDAR Vérifié</p>
        <p style="margin:0 0 .2em; font-size:8pt; color:#374151;">Contrat n° <strong>{$numLabel}</strong></p>
        <p style="margin:0 0 .15em; font-size:7pt; color:#6b7280;">Scannez le QR pour vérifier l'authenticité de ce document sur la plateforme UNIDAR.</p>
        <p style="margin:0; font-size:7pt; color:#9ca3af;">Généré le {$date} · UNIDAR · student.housing.community</p>
      </td>
    </tr>
  </table>
</div>
FOOTER : <<<NOFOOTER
<div style="margin-top:2.5em; border-top:1px solid #e5e7eb; padding-top:.6em; text-align:center; font-size:7pt; color:#9ca3af;">
  Document généré le {$date} · Plateforme UNIDAR · Contrat n° {$numLabel}
</div>
NOFOOTER;

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  @page { margin: 2cm 2.2cm; }
  body {
    font-family: Georgia, "Times New Roman", serif;
    font-size: 11pt;
    line-height: 1.75;
    color: #1f2937;
  }
  /* ── Body typography ── */
  h2 {
    text-align: center;
    font-size: 13pt;
    font-weight: bold;
    color: #1a1a2e;
    margin: .8em 0 1.2em;
    text-transform: uppercase;
    letter-spacing: .04em;
  }
  h3 {
    font-size: 10.5pt;
    font-weight: bold;
    color: #4f46e5;
    margin: 1.3em 0 .4em;
    border-bottom: 1px solid #e0e7ff;
    padding-bottom: .25em;
    text-transform: uppercase;
    letter-spacing: .02em;
  }
  p  { margin: .45em 0; }
  ul { margin: .4em 0 .4em 1.4em; padding: 0; }
  li { margin-bottom: .25em; }
  strong { color: #1a1a2e; }
  table { border-collapse: collapse; width: 100%; margin-top: 2em; }
  td    { border: 1px solid #d1d5db; padding: .85em 1em; vertical-align: top; width: 50%; }
  img   { max-height: 90px; max-width: 100%; display: block; margin: .5em auto; }
</style>
</head>
<body>

<!-- ═══ Document Header (native HTML table — reliable in DomPDF) ═══ -->
<table style="width:100%; border-collapse:collapse; margin:0 0 1.6em 0;
              border-bottom:3px solid #6366f1; padding-bottom:.8em;">
  <tr>
    <!-- Logo cell -->
    <td style="border:none; padding:0; vertical-align:middle; width:55%;">
      <table style="border-collapse:collapse; margin:0; width:auto;">
        <tr>
          <td style="border:none; padding:0; vertical-align:middle; width:54px;">
            {$shieldSvg}
          </td>
          <td style="border:none; padding:0 0 0 10px; vertical-align:middle;">
            <div style="font-size:22pt; font-weight:bold; color:#6366f1;
                        font-family:Georgia,serif; letter-spacing:3px; line-height:1.1;">UNIDAR</div>
            <div style="font-size:6.5pt; color:#64748b; letter-spacing:1.5px;
                        font-family:'Courier New',monospace; margin-top:3px;">
              student &middot; housing &middot; community
            </div>
          </td>
        </tr>
      </table>
    </td>
    <!-- Document info cell -->
    <td style="border:none; padding:0; vertical-align:middle; text-align:right;
               font-size:8.5pt; color:#374151; width:45%;">
      <strong style="font-size:9pt; color:#1a1a2e;">CONTRAT DE LOCATION</strong><br>
      R&eacute;f&eacute;rence : <strong>{$numLabel}</strong><br>
      Date d&apos;&eacute;mission : {$date}<br>
      <span style="display:inline-block; background:#ede9fe; color:#4f46e5;
                   font-size:6.5pt; padding:1px 7px; border-radius:3px;
                   font-weight:700; letter-spacing:.4px; margin-top:4px;">DOCUMENT OFFICIEL</span>
    </td>
  </tr>
</table>

<!-- ═══ Contract Body ═══ -->
{$body}

<!-- ═══ Footer ═══ -->
{$footer}
</body>
</html>
HTML;
    }

    /**
     * Request termination of a contract.
     */
    public function requestTermination(Contract $contract, User $user, string $reason): ContractTerminationRequest
    {
        $request = new ContractTerminationRequest();
        $request->setContract($contract);
        $request->setRequestedBy($user);
        $request->setReason($reason);
        $request->setStatus('pending');
        $request->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        return $request;
    }

    /**
     * Approve a termination request and cancel the contract.
     */
    public function approveTermination(ContractTerminationRequest $request): void
    {
        $request->setStatus('approved');
        $contract = $request->getContract();
        $contract->setStatus(ContractStatus::Cancelled);

        $this->cleanupAfterCancellation($contract);

        $this->entityManager->flush();

        $this->notifyContractTerminated($contract);
    }

    /**
     * Reject a termination request.
     */
    public function rejectTermination(ContractTerminationRequest $request): void
    {
        $request->setStatus('rejected');

        $this->entityManager->flush();
    }

    /**
     * Terminate (cancel) a contract directly.
     */
    public function terminateContract(Contract $contract): void
    {
        $contract->setStatus(ContractStatus::Cancelled);

        $this->cleanupAfterCancellation($contract);

        $this->entityManager->flush();
    }

    /**
     * Post-cancellation cleanup:
     *  - wipe the generated HTML contract and the student's signature (file + DB path)
     *  - free up the listing if its current active-contract count falls below its capacity
     *    (so it reappears on the listings page).
     *
     * The owner signature at the listing level is preserved — it belongs to the listing, not the contract.
     */
    private function cleanupAfterCancellation(Contract $contract): void
    {
        // --- 1. Delete student signature file ---
        $studentSigPath = $contract->getStudentSignaturePath();
        if ($studentSigPath) {
            $absolute = $this->projectDir . '/public/' . ltrim($studentSigPath, '/');
            if (is_file($absolute)) {
                @unlink($absolute);
            }
            $contract->setStudentSignaturePath(null);
        }

        // --- 2. Wipe the generated contract HTML and any stored file ---
        $contract->setContractContent(null);
        if (method_exists($contract, 'setContractFilePath')) {
            $filePath = method_exists($contract, 'getContractFilePath') ? $contract->getContractFilePath() : null;
            if ($filePath) {
                $absolute = $this->projectDir . '/public/' . ltrim($filePath, '/');
                if (is_file($absolute)) {
                    @unlink($absolute);
                }
            }
            $contract->setContractFilePath(null);
        }

        // --- 3. Re-open the listing if occupancy permits ---
        $listing = $contract->getListing();
        if ($listing) {
            $activeStatuses = [
                ContractStatus::Active,
                ContractStatus::Paid,
                ContractStatus::SignedByBoth,
                ContractStatus::SignedByStudent,
                ContractStatus::PendingSignature,
            ];
            $occupied = 0;
            foreach ($listing->getContracts() as $c) {
                if ($c->getId() === $contract->getId()) {
                    continue; // skip the one we just cancelled
                }
                if (in_array($c->getStatus(), $activeStatuses, true)) {
                    $occupied++;
                }
            }
            $capacity = $listing->getCapacity() ?? 1;
            // Only un-remove listings that were auto-closed; never revive a manually removed listing.
            if ($occupied < $capacity && $listing->getStatus() !== 'removed' && $listing->getStatus() !== 'active') {
                $listing->setStatus('active');
            }
        }
    }

    /**
     * Find all expired contracts and mark them as completed.
     *
     * @return int Number of contracts expired
     */
    public function checkAndExpireContracts(): int
    {
        $now = new \DateTimeImmutable();

        $qb = $this->entityManager->createQueryBuilder();
        $contracts = $qb->select('c')
            ->from(Contract::class, 'c')
            ->where('c.endDate < :now')
            ->andWhere('c.status IN (:statuses)')
            ->setParameter('now', $now)
            ->setParameter('statuses', [ContractStatus::Active, ContractStatus::Paid])
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($contracts as $contract) {
            $contract->setStatus(ContractStatus::Completed);
            $this->notifyContractExpired($contract);
            $count++;
        }

        if ($count > 0) {
            $this->entityManager->flush();
        }

        return $count;
    }

    // -------------------------------------------------------------------------
    // In-app notification helpers
    // -------------------------------------------------------------------------

    private function notifyContractGenerated(Contract $contract): void
    {
        $student = $contract->getStudent();
        $owner   = $contract->getOwner();
        $title   = $contract->getListing()->getTitle();
        $num     = $contract->getContractNumber();

        $this->notifier->notify(
            $student,
            "Bonjour {$student->getFullName()},\n\nVotre contrat n° {$num} pour le logement « {$title} » a été généré. Veuillez le signer dès que possible.\n\n— L'équipe UNIDAR"
        );

        $this->notifier->notify(
            $owner,
            "Bonjour {$owner->getFullName()},\n\nUn contrat n° {$num} a été généré pour votre logement « {$title} ». L'étudiant {$student->getFullName()} doit le signer.\n\n— L'équipe UNIDAR"
        );
    }

    private function notifyContractSigned(Contract $contract, User $signer, bool $signerIsStudent): void
    {
        $num = $contract->getContractNumber();

        if ($contract->getStatus() === ContractStatus::SignedByBoth) {
            $date = $contract->getStartDate()->format('d/m/Y');
            foreach ([$contract->getStudent(), $contract->getOwner()] as $recipient) {
                $this->notifier->notify(
                    $recipient,
                    "Bonjour {$recipient->getFullName()},\n\nLe contrat n° {$num} est désormais signé par les deux parties. Il prend effet le {$date}.\n\n— L'équipe UNIDAR"
                );
            }
            return;
        }

        $other = $signerIsStudent ? $contract->getOwner() : $contract->getStudent();
        $this->notifier->notify(
            $other,
            "Bonjour {$other->getFullName()},\n\n{$signer->getFullName()} a signé le contrat n° {$num}. Votre signature est maintenant requise pour finaliser le contrat.\n\n— L'équipe UNIDAR"
        );
    }

    private function notifyContractTerminated(Contract $contract): void
    {
        $num   = $contract->getContractNumber();
        $title = $contract->getListing()->getTitle();

        foreach ([$contract->getStudent(), $contract->getOwner()] as $recipient) {
            $this->notifier->notify(
                $recipient,
                "Bonjour {$recipient->getFullName()},\n\nLe contrat n° {$num} pour le logement « {$title} » a été résilié.\n\n— L'équipe UNIDAR"
            );
        }
    }

    private function notifyContractExpired(Contract $contract): void
    {
        $num  = $contract->getContractNumber();
        $date = $contract->getEndDate()->format('d/m/Y');

        foreach ([$contract->getStudent(), $contract->getOwner()] as $recipient) {
            $this->notifier->notify(
                $recipient,
                "Bonjour {$recipient->getFullName()},\n\nLe contrat n° {$num} est arrivé à échéance le {$date}.\n\n— L'équipe UNIDAR"
            );
        }
    }
}
