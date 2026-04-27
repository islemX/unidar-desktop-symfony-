<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Form\RegistrationType;
use App\Repository\UserRepository;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AuthController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('auth/login.html.twig', [
            'last_username' => $authUtils->getLastUsername(),
            'error'         => $authUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request                     $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface      $em,
        MailerService               $mailer,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $code = $this->generateCode();

            $user->setPassword($hasher->hashPassword($user, $form->get('password')->getData()));
            $user->setRole(UserRole::from($form->get('role')->getData()));
            $user->setStatus(UserStatus::Active);
            $user->setIsEmailVerified(false);
            $user->setEmailVerificationToken(null);           // token flow retired
            $user->setEmailVerificationCode($code);
            $user->setEmailVerificationCodeExpiresAt(new \DateTimeImmutable('+15 minutes'));

            $em->persist($user);
            $em->flush();

            $mailer->sendVerificationEmail($user, $code);

            return $this->redirectToRoute('app_verify_code', [
                'email' => $user->getEmail(),
            ]);
        }

        return $this->render('auth/register.html.twig', ['form' => $form]);
    }

    /**
     * Page where the user types the 6-digit code they received by email.
     */
    #[Route('/verify-code', name: 'app_verify_code', methods: ['GET', 'POST'])]
    public function verifyCode(
        Request                $request,
        UserRepository         $userRepo,
        EntityManagerInterface $em,
        MailerService          $mailer,
    ): Response {
        $email = $request->query->get('email', $request->request->get('email', ''));

        if ($request->isMethod('POST')) {
            $code = trim($request->request->getString('code'));
            $user = $userRepo->findOneBy(['email' => $email]);

            if (!$user) {
                $this->addFlash('error', 'No account found for that email.');
                return $this->redirectToRoute('app_verify_code', ['email' => $email]);
            }

            if ($user->isEmailVerified()) {
                $this->addFlash('info', 'Your email is already verified. You can sign in.');
                return $this->redirectToRoute('app_login');
            }

            if (!$user->isVerificationCodeValid($code)) {
                $this->addFlash('error', 'Invalid or expired code. Request a new one below.');
                return $this->redirectToRoute('app_verify_code', ['email' => $email]);
            }

            // ✅ Code is correct
            $user->setIsEmailVerified(true);
            $user->setEmailVerificationCode(null);
            $user->setEmailVerificationCodeExpiresAt(null);
            $em->flush();

            $mailer->sendWelcomeEmail($user);

            $this->addFlash('success', '🎉 Email verified! Your UNIDAR account is ready. Welcome!');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/verify_code.html.twig', ['email' => $email]);
    }

    /**
     * Resend a fresh 6-digit code.
     */
    #[Route('/resend-verification', name: 'app_resend_verification', methods: ['GET', 'POST'])]
    public function resendVerification(
        Request                $request,
        UserRepository         $userRepo,
        EntityManagerInterface $em,
        MailerService          $mailer,
    ): Response {
        if ($request->isMethod('POST')) {
            $email = trim($request->request->getString('email'));
            $user  = $userRepo->findOneBy(['email' => $email]);

            if ($user && !$user->isEmailVerified()) {
                $code = $this->generateCode();
                $user->setEmailVerificationCode($code);
                $user->setEmailVerificationCodeExpiresAt(new \DateTimeImmutable('+15 minutes'));
                $em->flush();
                $mailer->sendResendVerificationEmail($user, $code);
            }

            // Anti-enumeration: always show the same message
            $this->addFlash('info', 'If that email belongs to an unverified account, a new code has been sent.');
            return $this->redirectToRoute('app_verify_code', ['email' => $email]);
        }

        return $this->render('auth/resend_verification.html.twig');
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Intercepted by Symfony firewall
    }

    // ─────────────────────────────────────────────────────────────

    /** Generate a cryptographically random 6-digit code. */
    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
