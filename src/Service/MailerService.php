<?php

namespace App\Service;

use App\Entity\Contract;
use App\Entity\ContractTerminationRequest;
use App\Entity\Listing;
use App\Entity\Subscription;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Central service for all transactional emails sent by UNIDAR.
 *
 * ── Auth ──────────────────────────────────────────────────────────────
 *  sendVerificationEmail          Token verification link after register
 *  sendWelcomeEmail               Rich welcome after email confirmed
 *  sendResendVerificationEmail    Fresh verification link on demand
 *
 * ── Account status ────────────────────────────────────────────────────
 *  sendBanNotification            Permanent ban
 *  sendSuspensionNotification     Temporary suspension
 *
 * ── Subscriptions ─────────────────────────────────────────────────────
 *  sendSubscriptionConfirmation   Plan activated
 *  sendSubscriptionFailed         Payment failure
 *  sendSubscriptionExpired        Plan expired — renew CTA
 *
 * ── Contracts ─────────────────────────────────────────────────────────
 *  sendContractTerminationToOwner   Owner notified of student's request
 *  sendContractTerminationToStudent Confirmation to student who requested
 *  sendContractExpiringSoon         Warn student N days before contract end
 *
 * ── Listings ──────────────────────────────────────────────────────────
 *  sendListingApproved            Listing published
 *  sendListingRejected            Listing needs changes
 *  sendListingExpiringSoon        Warn owner N days before availability ends
 *
 * ── Identity verification ─────────────────────────────────────────────
 *  sendVerificationApproved       Identity docs approved
 *  sendVerificationRejected       Identity docs rejected
 *
 * ── Social / engagement ───────────────────────────────────────────────
 *  sendRoommateMatchFound         AI found N matches for a student
 *  sendNewMessageNotification     Unread message nudge (digest)
 *  sendRentReminder               Upcoming rent due alert
 */
class MailerService
{
    public function __construct(
        private readonly MailerInterface          $mailer,
        private readonly UrlGeneratorInterface    $urlGenerator,
        private readonly LoggerInterface          $logger,
        private readonly string                   $fromEmail,
        private readonly string                   $fromName,
        private readonly string                   $appName,
    ) {}

    // ─────────────────────────────────────────────────────────────
    // AUTH
    // ─────────────────────────────────────────────────────────────

    public function sendVerificationEmail(User $user, string $code): void
    {
        $this->send(
            $user,
            '✉️ Your UNIDAR verification code: ' . $code,
            'emails/verify_email.html.twig',
            ['user' => $user, 'code' => $code]
        );
    }

    public function sendWelcomeEmail(User $user): void
    {
        $this->send(
            $user,
            '🎉 Welcome to UNIDAR — Your account is ready!',
            'emails/welcome.html.twig',
            ['user' => $user]
        );
    }

    public function sendResendVerificationEmail(User $user, string $code): void
    {
        $this->send(
            $user,
            '🔁 New verification code for your UNIDAR account: ' . $code,
            'emails/verify_email.html.twig',
            ['user' => $user, 'code' => $code, 'is_resend' => true]
        );
    }

    // ─────────────────────────────────────────────────────────────
    // ACCOUNT STATUS
    // ─────────────────────────────────────────────────────────────

    public function sendBanNotification(User $user, string $cause = ''): void
    {
        $this->send(
            $user,
            '🚫 Your UNIDAR account has been suspended',
            'emails/account_status.html.twig',
            [
                'user'       => $user,
                'status'     => 'banned',
                'cause'      => $cause ?: 'Violation of our Terms of Service.',
                'appeal_url' => 'mailto:support@unidar.app',
            ]
        );
    }

    public function sendSuspensionNotification(User $user, string $cause = ''): void
    {
        $this->send(
            $user,
            '⏸️ Your UNIDAR account has been temporarily suspended',
            'emails/account_status.html.twig',
            [
                'user'       => $user,
                'status'     => 'suspended',
                'cause'      => $cause ?: 'Suspicious activity detected on your account.',
                'appeal_url' => 'mailto:support@unidar.app',
            ]
        );
    }

    // ─────────────────────────────────────────────────────────────
    // SUBSCRIPTIONS
    // ─────────────────────────────────────────────────────────────

    public function sendSubscriptionConfirmation(User $user, Subscription $subscription): void
    {
        $this->send(
            $user,
            '✅ Your UNIDAR subscription is active!',
            'emails/subscription.html.twig',
            ['user' => $user, 'subscription' => $subscription, 'status' => 'active']
        );
    }

    public function sendSubscriptionFailed(User $user, string $plan, string $error = ''): void
    {
        $this->send(
            $user,
            '❌ Payment failed — UNIDAR subscription',
            'emails/subscription.html.twig',
            [
                'user'   => $user,
                'status' => 'failed',
                'plan'   => $plan,
                'error'  => $error ?: 'The payment could not be processed.',
            ]
        );
    }

    public function sendSubscriptionExpired(User $user, Subscription $subscription): void
    {
        $this->send(
            $user,
            '⏰ Your UNIDAR subscription has expired — renew to keep full access',
            'emails/subscription_expired.html.twig',
            ['user' => $user, 'subscription' => $subscription]
        );
    }

    // ─────────────────────────────────────────────────────────────
    // CONTRACTS
    // ─────────────────────────────────────────────────────────────

    /**
     * Notify the owner when a student submits a termination request.
     */
    public function sendContractTerminationToOwner(
        User $owner,
        ContractTerminationRequest $request,
        User $student,
    ): void {
        $this->send(
            $owner,
            '📋 Termination request received — UNIDAR contract #' . $request->getContract()->getContractNumber(),
            'emails/contract_termination.html.twig',
            [
                'recipient'      => $owner,
                'recipient_role' => 'owner',
                'requester'      => $student,
                'request'        => $request,
                'contract'       => $request->getContract(),
            ]
        );
    }

    /**
     * Confirm to the student that their termination request was sent.
     */
    public function sendContractTerminationToStudent(
        User $student,
        ContractTerminationRequest $request,
    ): void {
        $this->send(
            $student,
            '📋 Your termination request has been sent — UNIDAR',
            'emails/contract_termination.html.twig',
            [
                'recipient'      => $student,
                'recipient_role' => 'student',
                'requester'      => $student,
                'request'        => $request,
                'contract'       => $request->getContract(),
            ]
        );
    }

    /**
     * Warn the student N days before their contract end date.
     */
    public function sendContractExpiringSoon(User $student, Contract $contract, int $daysLeft): void
    {
        $this->send(
            $student,
            sprintf('📅 Your rental contract ends in %d day%s — UNIDAR', $daysLeft, $daysLeft !== 1 ? 's' : ''),
            'emails/contract_expiring.html.twig',
            ['user' => $student, 'contract' => $contract, 'daysLeft' => $daysLeft]
        );
    }

    // ─────────────────────────────────────────────────────────────
    // LISTINGS
    // ─────────────────────────────────────────────────────────────

    public function sendListingApproved(User $owner, Listing $listing): void
    {
        $listingUrl = $this->urlGenerator->generate(
            'listing_show',
            ['id' => $listing->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $this->send(
            $owner,
            '✅ Your listing has been approved — UNIDAR',
            'emails/listing_status.html.twig',
            ['user' => $owner, 'listing' => $listing, 'status' => 'approved', 'listing_url' => $listingUrl]
        );
    }

    public function sendListingRejected(User $owner, Listing $listing, string $reason = ''): void
    {
        $editUrl = $this->urlGenerator->generate(
            'listing_edit',
            ['id' => $listing->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $this->send(
            $owner,
            '❌ Your listing needs changes — UNIDAR',
            'emails/listing_status.html.twig',
            [
                'user'     => $owner,
                'listing'  => $listing,
                'status'   => 'rejected',
                'reason'   => $reason ?: 'The listing did not meet our quality standards.',
                'edit_url' => $editUrl,
            ]
        );
    }

    /**
     * Warn the owner N days before their listing's available-until date.
     */
    public function sendListingExpiringSoon(User $owner, Listing $listing, int $daysLeft): void
    {
        $this->send(
            $owner,
            sprintf('🏠 Your listing expires in %d day%s — UNIDAR', $daysLeft, $daysLeft !== 1 ? 's' : ''),
            'emails/listing_expiring.html.twig',
            ['user' => $owner, 'listing' => $listing, 'daysLeft' => $daysLeft]
        );
    }

    // ─────────────────────────────────────────────────────────────
    // IDENTITY VERIFICATION
    // ─────────────────────────────────────────────────────────────

    public function sendVerificationApproved(User $user): void
    {
        $this->send(
            $user,
            '✅ Identity verified — UNIDAR',
            'emails/identity_verification.html.twig',
            ['user' => $user, 'status' => 'approved']
        );
    }

    public function sendVerificationRejected(User $user, string $reason = ''): void
    {
        $this->send(
            $user,
            '❌ Identity verification failed — UNIDAR',
            'emails/identity_verification.html.twig',
            [
                'user'   => $user,
                'status' => 'rejected',
                'reason' => $reason ?: 'Your documents could not be verified. Please resubmit.',
            ]
        );
    }

    // ─────────────────────────────────────────────────────────────
    // SOCIAL / ENGAGEMENT
    // ─────────────────────────────────────────────────────────────

    /**
     * @param array<array{student: User, score: float, traits?: string[]}> $matches
     */
    public function sendRoommateMatchFound(User $student, array $matches): void
    {
        if (empty($matches)) {
            return;
        }

        $this->send(
            $student,
            sprintf('🤝 %d new roommate match%s found for you — UNIDAR', count($matches), count($matches) !== 1 ? 'es' : ''),
            'emails/roommate_match.html.twig',
            [
                'user'       => $student,
                'matches'    => $matches,
                'matchCount' => count($matches),
            ]
        );
    }

    /**
     * Nudge email when a user has an unread message.
     */
    public function sendNewMessageNotification(
        User $recipient,
        User $sender,
        string $messagePreview,
        ?Listing $listing = null,
    ): void {
        $this->send(
            $recipient,
            sprintf('💬 New message from %s — UNIDAR', $sender->getFullName()),
            'emails/new_message.html.twig',
            [
                'recipient'      => $recipient,
                'sender'         => $sender,
                'messagePreview' => mb_strimwidth($messagePreview, 0, 160, '…'),
                'listing'        => $listing,
            ]
        );
    }

    /**
     * Remind a student that rent is due in N days.
     */
    public function sendRentReminder(
        User $student,
        Contract $contract,
        \DateTimeInterface $dueDate,
        int $daysUntilDue,
    ): void {
        $this->send(
            $student,
            sprintf('💳 Rent reminder — due in %d day%s', $daysUntilDue, $daysUntilDue !== 1 ? 's' : ''),
            'emails/rent_reminder.html.twig',
            [
                'user'         => $student,
                'contract'     => $contract,
                'dueDate'      => $dueDate,
                'daysUntilDue' => $daysUntilDue,
            ]
        );
    }

    // ─────────────────────────────────────────────────────────────
    // INTERNAL
    // ─────────────────────────────────────────────────────────────

    /**
     * Email the fully-signed contract PDF to a recipient as an attachment.
     *
     * @param string $pdfContent  Raw PDF binary from DomPDF
     * @param string $filename    e.g. "contract-55c4....pdf"
     */
    public function sendContractPdf(User $recipient, Contract $contract, string $pdfContent, string $filename): void
    {
        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($recipient->getEmail(), $recipient->getFullName()))
                ->subject('📄 Your UNIDAR Contract — ' . substr($contract->getContractNumber() ?? '', 0, 8) . '...')
                ->htmlTemplate('emails/contract_signed.html.twig')
                ->context([
                    'app_name'        => $this->appName,
                    'recipient_email' => $recipient->getEmail(),
                    'recipient'       => $recipient,
                    'contract'        => $contract,
                ])
                ->attach($pdfContent, $filename, 'application/pdf');

            $this->mailer->send($email);
            $this->logger->info('Contract PDF emailed', ['to' => $recipient->getEmail()]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to email contract PDF', [
                'to'    => $recipient->getEmail(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function send(User $recipient, string $subject, string $template, array $context = []): void
    {
        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($recipient->getEmail(), $recipient->getFullName()))
                ->subject($subject)
                ->htmlTemplate($template)
                ->context(array_merge($context, [
                    'app_name'        => $this->appName,
                    'recipient_email' => $recipient->getEmail(),
                ]));

            $this->mailer->send($email);

            $this->logger->info('Email sent', [
                'to'       => $recipient->getEmail(),
                'template' => $template,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send email', [
                'to'       => $recipient->getEmail(),
                'template' => $template,
                'error'    => $e->getMessage(),
            ]);
            // Never throw — email failures must never break user-facing flows
        }
    }
}
