<?php

namespace App\Bundle\ContractTemplate\Command;

use App\Entity\ContractTemplate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Seeds one default contract template per locale: fr, en, ar.
 * Safe to re-run — skips locales that already have a template.
 */
#[AsCommand(name: 'app:seed:contract-templates', description: 'Seed default EN/FR/AR contract templates to the database')]
class SeedContractTemplatesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seeding Contract Templates');

        $templates = $this->defaultTemplates();

        foreach ($templates as ['locale' => $locale, 'name' => $name, 'content' => $content]) {
            $existing = $this->em->getRepository(ContractTemplate::class)->findOneBy(['locale' => $locale]);
            if ($existing) {
                $io->text("  ↳ Skipped locale '{$locale}' — already exists.");
                continue;
            }

            $t = new ContractTemplate();
            $t->setTemplateName($name);
            $t->setTemplateType('residential');
            $t->setLocale($locale);
            $t->setVersion(1);
            $t->setIsDefault(true);
            $t->setIsActive(true);
            $t->setContractContent($content);
            $t->setLegalClauses('');
            $t->setTunisiaSpecificClauses('');

            $this->em->persist($t);
            $io->text("  ✓ Created locale '{$locale}': {$name}");
        }

        $this->em->flush();
        $io->success('Done! Templates available at /admin/contract-templates');

        return Command::SUCCESS;
    }

    private function defaultTemplates(): array
    {
        return [
            [
                'locale'  => 'fr',
                'name'    => 'Contrat Standard FR',
                'content' => $this->frTemplate(),
            ],
            [
                'locale'  => 'en',
                'name'    => 'Standard Lease EN',
                'content' => $this->enTemplate(),
            ],
            [
                'locale'  => 'ar',
                'name'    => 'عقد إيجار السكن الطلابي AR',
                'content' => $this->arTemplate(),
            ],
        ];
    }

    // ── Template bodies ───────────────────────────────────────────────────────
    // These are Twig string templates — placeholders use {{ variable }} syntax.
    // Available context vars: listing, owner, student, start, end, duration_months, type_str

    private function frTemplate(): string
    {
        return <<<'TWIG'
<div style="font-family:Georgia,serif;line-height:1.7;color:#111;">
  <h2 style="text-align:center;margin-bottom:1.5rem;">CONTRAT DE LOCATION RÉSIDENTIELLE</h2>
  <p><strong>ENTRE LES SOUSSIGNÉS :</strong></p>
  <p>
    <strong>Propriétaire :</strong> {{ owner.fullName }}<br>
    Email : {{ owner.email }}<br>
    Ci-après dénommé « le Propriétaire »
  </p>
  <p><strong>ET</strong></p>
  <p>
    <strong>Étudiant :</strong> {{ student.fullName }}<br>
    Email : {{ student.email }}<br>
    Ci-après dénommé « l'Étudiant »
  </p>
  <h3>ARTICLE 1 : OBJET</h3>
  <p>Location d'un logement étudiant : <strong>{{ listing.title }}</strong>, {{ listing.address }}.</p>
  <h3>ARTICLE 2 : DURÉE</h3>
  <p><strong>{{ duration_months }} mois</strong> — du {{ start|date('d/m/Y') }} au {{ end|date('d/m/Y') }}.</p>
  <h3>ARTICLE 3 : LOYER</h3>
  <p>Loyer mensuel : <strong>{{ listing.price }} TND</strong>.</p>
  <h3>ARTICLE 4 : RÉSILIATION</h3>
  <p>Préavis de 30 jours requis. Soumis au droit tunisien.</p>
  <table style="width:100%;margin-top:2.5rem;border-collapse:collapse;">
    <tr>
      <td style="width:50%;padding:1rem;border:1px solid #e5e7eb;">
        <p style="font-size:.8rem;color:#64748b;font-weight:700;text-transform:uppercase;">Signature du Propriétaire</p>
        <div style="min-height:120px;"><!--OWNER_SIGNATURE--></div>
        <p style="font-weight:600;">{{ owner.fullName }}</p>
      </td>
      <td style="width:50%;padding:1rem;border:1px solid #e5e7eb;">
        <p style="font-size:.8rem;color:#64748b;font-weight:700;text-transform:uppercase;">Signature de l'Étudiant</p>
        <div style="min-height:120px;"><!--STUDENT_SIGNATURE--></div>
        <p style="font-weight:600;">{{ student.fullName }}</p>
      </td>
    </tr>
  </table>
</div>
TWIG;
    }

    private function enTemplate(): string
    {
        return <<<'TWIG'
<div style="font-family:Georgia,serif;line-height:1.7;color:#111;">
  <h2 style="text-align:center;margin-bottom:1.5rem;">STUDENT RESIDENTIAL LEASE AGREEMENT</h2>
  <p><strong>BETWEEN THE PARTIES:</strong></p>
  <p>
    <strong>Landlord:</strong> {{ owner.fullName }}<br>
    Email: {{ owner.email }}<br>
    Hereinafter referred to as "the Landlord"
  </p>
  <p><strong>AND</strong></p>
  <p>
    <strong>Student:</strong> {{ student.fullName }}<br>
    Email: {{ student.email }}<br>
    Hereinafter referred to as "the Student"
  </p>
  <h3>ARTICLE 1 — PROPERTY</h3>
  <p><strong>{{ listing.title }}</strong>, {{ listing.address }}. Type: {{ type_str }}.</p>
  <h3>ARTICLE 2 — TERM</h3>
  <p><strong>{{ duration_months }} months</strong> — from {{ start|date('d/m/Y') }} to {{ end|date('d/m/Y') }}.</p>
  <h3>ARTICLE 3 — RENT</h3>
  <p>Monthly rent: <strong>{{ listing.price }} TND</strong>, payable at month end.</p>
  <h3>ARTICLE 4 — TERMINATION</h3>
  <p>30 days written notice required. Subject to Tunisian law.</p>
  <table style="width:100%;margin-top:2.5rem;border-collapse:collapse;">
    <tr>
      <td style="width:50%;padding:1rem;border:1px solid #e5e7eb;">
        <p style="font-size:.8rem;color:#64748b;font-weight:700;text-transform:uppercase;">Landlord Signature</p>
        <div style="min-height:120px;"><!--OWNER_SIGNATURE--></div>
        <p style="font-weight:600;">{{ owner.fullName }}</p>
      </td>
      <td style="width:50%;padding:1rem;border:1px solid #e5e7eb;">
        <p style="font-size:.8rem;color:#64748b;font-weight:700;text-transform:uppercase;">Student Signature</p>
        <div style="min-height:120px;"><!--STUDENT_SIGNATURE--></div>
        <p style="font-weight:600;">{{ student.fullName }}</p>
      </td>
    </tr>
  </table>
</div>
TWIG;
    }

    private function arTemplate(): string
    {
        return <<<'TWIG'
<div dir="rtl" style="font-family:Arial,sans-serif;line-height:1.9;color:#111;">
  <h2 style="text-align:center;margin-bottom:1.5rem;">عقد إيجار سكن طلابي</h2>
  <p><strong>بين الطرفين:</strong></p>
  <p>
    <strong>المؤجر:</strong> {{ owner.fullName }}<br>
    البريد الإلكتروني: {{ owner.email }}<br>
    يُشار إليه فيما بعد بـ «المالك»
  </p>
  <p><strong>و</strong></p>
  <p>
    <strong>المستأجر (الطالب):</strong> {{ student.fullName }}<br>
    البريد الإلكتروني: {{ student.email }}<br>
    يُشار إليه فيما بعد بـ «الطالب»
  </p>
  <h3>المادة الأولى — الوحدة السكنية</h3>
  <p>{{ listing.title }}، {{ listing.address }}. النوع: {{ type_str }}.</p>
  <h3>المادة الثانية — مدة العقد</h3>
  <p><strong>{{ duration_months }} أشهر</strong> — من {{ start|date('d/m/Y') }} إلى {{ end|date('d/m/Y') }}.</p>
  <h3>المادة الثالثة — الإيجار</h3>
  <p>الإيجار الشهري: <strong>{{ listing.price }} دينار تونسي</strong>، يُدفع آخر الشهر.</p>
  <h3>المادة الرابعة — الفسخ</h3>
  <p>يُلزم إشعار مدته 30 يومًا. يخضع هذا العقد للقانون التونسي.</p>
  <table style="width:100%;margin-top:2.5rem;border-collapse:collapse;">
    <tr>
      <td style="width:50%;padding:1rem;border:1px solid #e5e7eb;text-align:center;">
        <p style="font-size:.8rem;color:#64748b;font-weight:700;">توقيع المالك</p>
        <div style="min-height:120px;"><!--OWNER_SIGNATURE--></div>
        <p style="font-weight:600;">{{ owner.fullName }}</p>
      </td>
      <td style="width:50%;padding:1rem;border:1px solid #e5e7eb;text-align:center;">
        <p style="font-size:.8rem;color:#64748b;font-weight:700;">توقيع الطالب</p>
        <div style="min-height:120px;"><!--STUDENT_SIGNATURE--></div>
        <p style="font-weight:600;">{{ student.fullName }}</p>
      </td>
    </tr>
  </table>
</div>
TWIG;
    }
}
