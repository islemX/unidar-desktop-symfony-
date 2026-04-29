<?php

namespace App\Bundle\ContractTemplate\Service;

use App\Entity\Contract;
use App\Entity\ContractTemplate;
use App\Repository\ContractTemplateRepository;
use Psr\Log\LoggerInterface;
use Twig\Environment;

/**
 * TemplateRendererService
 *
 * Loads the active ContractTemplate for the requested locale,
 * renders it as a Twig string template with contract/listing/user variables,
 * and returns the final HTML body ready for Dompdf or display.
 */
class TemplateRendererService
{
    public function __construct(
        private readonly ContractTemplateRepository $templateRepo,
        private readonly Environment                $twig,
        private readonly LoggerInterface            $logger,
    ) {}

    /**
     * Render the active contract template for the given locale.
     * Falls back to hardcoded French body if no template is found.
     *
     * @param Contract $contract   The contract entity with all relations loaded
     * @param string   $locale     'en' | 'fr' | 'ar'
     * @return string              Final HTML body (without the PDF wrapper)
     */
    public function render(Contract $contract, string $locale = 'fr'): string
    {
        $template = $this->findTemplate($locale);

        if (!$template) {
            $this->logger->info("ContractTemplate: no template for locale '{$locale}', using built-in fallback.");
            return $this->builtInFrFallback($contract);
        }

        try {
            $listing = $contract->getListing();
            $owner   = $contract->getOwner();
            $student = $contract->getStudent();
            $start   = $contract->getStartDate();
            $end     = $contract->getEndDate();
            $months  = $start && $end
                ? (int) $start->diff($end)->m + ($start->diff($end)->y * 12)
                : 12;

            $type    = $listing?->getPropertyType();
            $typeStr = $type instanceof \BackedEnum ? $type->value : (string) $type;

            $context = [
                'contract'      => $contract,
                'listing'       => $listing,
                'owner'         => $owner,
                'student'       => $student,
                'start'         => $start,
                'end'           => $end,
                'duration_months' => $months,
                'type_str'      => $typeStr,
                'locale'        => $locale,
            ];

            // Render a Twig *string* template (not a file).
            // The template content is stored in the DB.
            return $this->twig->createTemplate($template->getContractContent())->render($context);

        } catch (\Throwable $e) {
            $this->logger->error('ContractTemplate render failed: ' . $e->getMessage());
            return $this->builtInFrFallback($contract);
        }
    }

    /**
     * Find the active template for the given locale, with fallbacks:
     *   requested locale → 'fr' → any active template.
     */
    private function findTemplate(string $locale): ?ContractTemplate
    {
        // Try exact match with is_default flag first
        $template = $this->templateRepo->findOneBy([
            'locale'    => $locale,
            'isActive'  => true,
            'isDefault' => true,
        ]);

        if (!$template) {
            $template = $this->templateRepo->findOneBy(['locale' => $locale, 'isActive' => true]);
        }

        if (!$template && $locale !== 'fr') {
            $template = $this->templateRepo->findOneBy(['locale' => 'fr', 'isActive' => true]);
        }

        if (!$template) {
            $template = $this->templateRepo->findOneBy(['isActive' => true]);
        }

        return $template;
    }

    /**
     * Built-in French template — identical to the original ContractManager body.
     * Used when no DB template exists for the requested locale.
     */
    private function builtInFrFallback(Contract $contract): string
    {
        $e       = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $listing = $contract->getListing();
        $owner   = $contract->getOwner();
        $student = $contract->getStudent();
        $start   = $contract->getStartDate();
        $end     = $contract->getEndDate();
        $months  = $start && $end
            ? (int) $start->diff($end)->m + ($start->diff($end)->y * 12)
            : 12;
        $type    = $listing?->getPropertyType();
        $typeStr = $type instanceof \BackedEnum ? $type->value : (string) $type;

        return <<<HTML
<div style="font-family: Georgia, serif; line-height: 1.7; color: #111;">
  <h2 style="text-align:center; margin-bottom:1.5rem;">CONTRAT DE LOCATION RÉSIDENTIELLE</h2>
  <p><strong>ENTRE LES SOUSSIGNÉS :</strong></p>
  <p>
    <strong>Propriétaire :</strong> {$e($owner?->getFullName())}<br>
    Email : {$e($owner?->getEmail())}<br>
    Ci-après dénommé « le Propriétaire »
  </p>
  <p><strong>ET</strong></p>
  <p>
    <strong>Étudiant :</strong> {$e($student?->getFullName())}<br>
    Email : {$e($student?->getEmail())}<br>
    Ci-après dénommé « l'Étudiant »
  </p>
  <p><strong>IL A ÉTÉ CONVENU ET ARRÊTÉ CE QUI SUIT :</strong></p>
  <h3>ARTICLE 1 : OBJET DU CONTRAT</h3>
  <p>Le présent contrat a pour objet la location d'un logement à usage de résidence étudiante.</p>
  <h3>ARTICLE 2 : DESCRIPTION DU LOGEMENT</h3>
  <ul>
    <li>Titre : {$e($listing?->getTitle())}</li>
    <li>Adresse : {$e($listing?->getAddress())}</li>
    <li>Type : {$e($typeStr)}</li>
    <li>Prix mensuel : {$e($listing?->getPrice())} TND</li>
    <li>Chambres : {$e($listing?->getBedrooms())}</li>
  </ul>
  <h3>ARTICLE 3 : DURÉE DU CONTRAT</h3>
  <p>La présente location est consentie pour une durée de <strong>{$months} mois</strong>,
     à compter du <strong>{$start?->format('d/m/Y')}</strong> au <strong>{$end?->format('d/m/Y')}</strong>.</p>
  <h3>ARTICLE 4 : LOYER ET CHARGES</h3>
  <p>Le loyer mensuel est fixé à <strong>{$e($listing?->getPrice())} TND</strong>.</p>
  <h3>ARTICLE 5 : RÉSILIATION</h3>
  <p>Résiliation possible avec un préavis de 30 jours.</p>
  <h3>ARTICLE 6 : DROIT APPLICABLE</h3>
  <p>Le présent contrat est soumis au droit tunisien en vigueur.</p>
  <p style="margin-top:2rem;">Fait en deux exemplaires, à Tunis, le {$start?->format('d/m/Y')}.</p>
  <table style="width:100%; margin-top:2.5rem; border-collapse:collapse;">
    <tr>
      <td style="width:50%; vertical-align:top; padding:1rem; border:1px solid #e5e7eb;">
        <p style="margin:0 0 .25rem; font-size:.8rem; color:#64748b; text-transform:uppercase; font-weight:700;">Signature du Propriétaire</p>
        <div style="min-height:120px; display:flex; align-items:center; justify-content:center;"><!--OWNER_SIGNATURE--></div>
        <p style="margin:.5rem 0 0; font-weight:600;">{$e($owner?->getFullName())}</p>
      </td>
      <td style="width:50%; vertical-align:top; padding:1rem; border:1px solid #e5e7eb;">
        <p style="margin:0 0 .25rem; font-size:.8rem; color:#64748b; text-transform:uppercase; font-weight:700;">Signature de l'Étudiant</p>
        <div style="min-height:120px; display:flex; align-items:center; justify-content:center;"><!--STUDENT_SIGNATURE--></div>
        <p style="margin:.5rem 0 0; font-weight:600;">{$e($student?->getFullName())}</p>
      </td>
    </tr>
  </table>
</div>
HTML;
    }
}
