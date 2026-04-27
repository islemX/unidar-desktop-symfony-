<?php

namespace App\Command;

use App\Entity\Contract;
use App\Entity\Listing;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\SubscriptionPlan;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Service\MailerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Send a preview of any UNIDAR email template to a real inbox.
 *
 * Usage:
 *   php bin/console app:email-preview welcome you@example.com
 *   php bin/console app:email-preview all    you@example.com
 */
#[AsCommand(
    name: 'app:email-preview',
    description: 'Send a preview of UNIDAR email templates to a real inbox.',
)]
class PreviewEmailCommand extends Command
{
    private const TEMPLATES = [
        'welcome',
        'verify',
        'account-ban',
        'account-suspend',
        'subscription-active',
        'subscription-failed',
        'subscription-expired',
        'listing-approved',
        'listing-rejected',
        'listing-expiring',
        'contract-termination',
        'contract-expiring',
        'identity-approved',
        'identity-rejected',
        'roommate-match',
        'new-message',
        'rent-reminder',
    ];

    public function __construct(private readonly MailerService $mailer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('template', InputArgument::REQUIRED,
                'Template to preview: ' . implode(', ', self::TEMPLATES) . ', or "all"')
            ->addArgument('email', InputArgument::REQUIRED, 'Recipient email address')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Recipient name', 'Islem Ben Amor');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io       = new SymfonyStyle($input, $output);
        $template = $input->getArgument('template');
        $email    = $input->getArgument('email');
        $name     = $input->getOption('name');

        $templates = $template === 'all' ? self::TEMPLATES : [$template];

        foreach ($templates as $tpl) {
            $this->sendPreview($tpl, $email, $name, $io);
        }

        $io->success(sprintf('Preview(s) sent to %s — check your inbox!', $email));
        return Command::SUCCESS;
    }

    private function sendPreview(string $template, string $email, string $name, SymfonyStyle $io): void
    {
        $user = $this->mockUser($email, $name);

        try {
            match ($template) {
                'welcome'              => $this->mailer->sendWelcomeEmail($user),
                'verify'               => $this->mailer->sendVerificationEmail($user, bin2hex(random_bytes(16))),
                'account-ban'          => $this->mailer->sendBanNotification($user, 'Repeated violation of listing quality standards.'),
                'account-suspend'      => $this->mailer->sendSuspensionNotification($user, 'Suspicious login activity detected.'),
                'subscription-active'  => $this->mailer->sendSubscriptionConfirmation($user, $this->mockSubscription($user, 'monthly')),
                'subscription-failed'  => $this->mailer->sendSubscriptionFailed($user, 'monthly', 'Insufficient funds on your card.'),
                'subscription-expired' => $this->mailer->sendSubscriptionExpired($user, $this->mockSubscription($user, 'monthly', expired: true)),
                'listing-approved'     => $this->mailer->sendListingApproved($user, $this->mockListing()),
                'listing-rejected'     => $this->mailer->sendListingRejected($user, $this->mockListing(), 'Photos are too dark. Please upload clearer images.'),
                'listing-expiring'     => $this->mailer->sendListingExpiringSoon($user, $this->mockListing(), 5),
                'contract-termination' => $this->mailer->sendContractTerminationToStudent($user, $this->mockTerminationRequest()),
                'contract-expiring'    => $this->mailer->sendContractExpiringSoon($user, $this->mockContract($user), 14),
                'identity-approved'    => $this->mailer->sendVerificationApproved($user),
                'identity-rejected'    => $this->mailer->sendVerificationRejected($user, 'National ID photo is blurry and partially cropped.'),
                'roommate-match'       => $this->mailer->sendRoommateMatchFound($user, $this->mockMatches()),
                'new-message'          => $this->mailer->sendNewMessageNotification($user, $this->mockSender(), 'Hi! I saw your listing near ESPRIT and I\'m very interested. Is the room still available?', $this->mockListing()),
                'rent-reminder'        => $this->mailer->sendRentReminder($user, $this->mockContract($user), new \DateTimeImmutable('+5 days'), 5),
                default                => throw new \InvalidArgumentException("Unknown template: $template"),
            };
            $io->writeln("  ✅ <info>$template</info> sent");
        } catch (\Throwable $e) {
            $io->writeln("  ❌ <error>$template</error>: " . $e->getMessage());
        }
    }

    // ── Mock helpers ─────────────────────────────────────────────

    private function mockUser(string $email, string $name, string $role = 'student'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFullName($name);
        $user->setRole(UserRole::Student);
        $user->setStatus(UserStatus::Active);
        $user->setIsEmailVerified(true);
        return $user;
    }

    private function mockSender(): User
    {
        $sender = new User();
        $sender->setEmail('owner@demo.com');
        $sender->setFullName('Mohamed Ali');
        $sender->setRole(UserRole::Owner);
        $sender->setStatus(UserStatus::Active);
        $sender->setIsEmailVerified(true);
        return $sender;
    }

    private function mockSubscription(User $user, string $plan, bool $expired = false): Subscription
    {
        $sub = new Subscription();
        $sub->setUser($user);
        $sub->setPlan(SubscriptionPlan::from($plan));
        $sub->setAmount($plan === 'monthly' ? '29.00' : '249.00');
        $sub->setStatus($expired ? 'expired' : 'active');
        $sub->setPaymentMethod('credit_card');
        $sub->setStartsAt(new \DateTimeImmutable('-1 month'));
        $sub->setExpiresAt($expired ? new \DateTimeImmutable('-1 day') : new \DateTimeImmutable('+1 month'));
        return $sub;
    }

    private function mockListing(): Listing
    {
        $listing = new Listing();
        // Use reflection to set private fields without setters requiring validation
        $r = new \ReflectionClass($listing);
        $this->setField($r, $listing, 'title', 'Chambre meublée — Résidence Les Jardins, Ariana');
        $this->setField($r, $listing, 'address', 'Rue de la Liberté, Ariana Ville, Tunis');
        $this->setField($r, $listing, 'price', '350.00');
        $this->setField($r, $listing, 'bedrooms', 1);
        $this->setField($r, $listing, 'bathrooms', 1);
        $this->setField($r, $listing, 'id', 42);
        return $listing;
    }

    private function mockContract(User $student): Contract
    {
        $owner = $this->mockSender();
        $listing = $this->mockListing();

        $contract = new Contract();
        $r = new \ReflectionClass($contract);
        $this->setField($r, $contract, 'contractNumber', 'CTR-2025-00042');
        $this->setField($r, $contract, 'monthlyRent', '350.00');
        $this->setField($r, $contract, 'securityDeposit', '700.00');
        $this->setField($r, $contract, 'startDate', new \DateTime('2025-09-01'));
        $this->setField($r, $contract, 'endDate', new \DateTime('2026-07-01'));
        $this->setField($r, $contract, 'student', $student);
        $this->setField($r, $contract, 'owner', $owner);
        $this->setField($r, $contract, 'listing', $listing);
        $this->setField($r, $contract, 'id', 42);
        return $contract;
    }

    private function mockTerminationRequest(): \App\Entity\ContractTerminationRequest
    {
        $user = $this->mockUser('student@demo.com', 'Islem Ben Amor');
        $contract = $this->mockContract($user);

        $req = new \App\Entity\ContractTerminationRequest();
        $r   = new \ReflectionClass($req);
        $this->setField($r, $req, 'reason', 'I have been offered accommodation at the university residence and need to vacate early.');
        $this->setField($r, $req, 'contract', $contract);
        $this->setField($r, $req, 'requestedBy', $user);
        return $req;
    }

    private function mockMatches(): array
    {
        return [
            [
                'student' => tap($this->mockUser('a@demo.com', 'Sara Khaled'), fn($u) => $u->setEmail('a@demo.com')),
                'score'   => 0.91,
                'traits'  => ['Same sleep schedule', 'Non-smoker', 'Similar budget', 'Clean lifestyle'],
            ],
            [
                'student' => tap($this->mockUser('b@demo.com', 'Yassine Trabelsi'), fn($u) => $u->setEmail('b@demo.com')),
                'score'   => 0.76,
                'traits'  => ['Non-smoker', 'Similar budget'],
            ],
            [
                'student' => tap($this->mockUser('c@demo.com', 'Mariem Bouaziz'), fn($u) => $u->setEmail('c@demo.com')),
                'score'   => 0.63,
                'traits'  => ['Same university area'],
            ],
        ];
    }

    private function setField(\ReflectionClass $r, object $obj, string $field, mixed $value): void
    {
        try {
            $prop = $r->getProperty($field);
            $prop->setAccessible(true);
            $prop->setValue($obj, $value);
        } catch (\ReflectionException) {
            // Field doesn't exist in this entity — skip silently
        }
    }
}

/**
 * Functional tap() helper — applies a callback and returns the original value.
 */
function tap(mixed $value, callable $callback): mixed
{
    $callback($value);
    return $value;
}
