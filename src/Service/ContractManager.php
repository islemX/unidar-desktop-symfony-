<?php

namespace App\Service;

use App\Entity\Contract;
use App\Entity\ContractTemplate;
use App\Entity\ContractTerminationRequest;
use App\Entity\Listing;
use App\Entity\User;
use App\Enum\ContractStatus;
use App\Repository\ContractTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class ContractManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ContractTemplateRepository $contractTemplateRepository,
        private string $projectDir,
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

        $html = $this->renderContractHtml(
            $listing,
            $listing->getOwner(),
            $student,
            $start,
            $end,
            $durationMonths
        );

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
    }

    /**
     * Return the contract content HTML string for rendering/download.
     */
    public function downloadContract(Contract $contract): string
    {
        return $contract->getContractContent() ?? '';
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
        $request->getContract()->setStatus(ContractStatus::Cancelled);

        $this->entityManager->flush();
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

        $this->entityManager->flush();
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
            $count++;
        }

        if ($count > 0) {
            $this->entityManager->flush();
        }

        return $count;
    }
}
