<?php

namespace App\Controller;

use App\Entity\Subscription;
use App\Enum\SubscriptionPlan;
use App\Repository\SubscriptionRepository;
use App\Service\FakePaymentGateway;
use App\Service\InAppNotificationService;
use App\Service\MailerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/subscription')]
#[IsGranted('ROLE_USER')]
class SubscriptionController extends AbstractController
{
    #[Route('', name: 'subscription_index')]
    public function index(SubscriptionRepository $subscriptionRepo): Response
    {
        $current = $subscriptionRepo->findActiveByUser($this->getUser());
        return $this->render('subscription/index.html.twig', ['current' => $current]);
    }

    #[Route('/subscribe', name: 'subscription_create', methods: ['POST'])]
    public function subscribe(
        Request $request,
        EntityManagerInterface $em,
        FakePaymentGateway $gateway,
        SubscriptionRepository $subscriptionRepo,
        InAppNotificationService $notifier,
        MailerService $mailer,
    ): Response {
        $planValue = $request->request->get('plan', 'monthly');
        $cardNumber = $request->request->get('cardNumber', '');

        try {
            $plan = SubscriptionPlan::from($planValue);
        } catch (\ValueError) {
            $this->addFlash('error', 'Invalid plan selected.');
            return $this->redirectToRoute('subscription_index');
        }

        $amount = $plan->getPrice();
        $result = $gateway->processPayment($amount, 'credit_card');

        if ($result['success']) {
            $last4 = strlen($cardNumber) >= 4 ? substr($cardNumber, -4) : null;

            $now = new \DateTimeImmutable();
            $expires = $plan === SubscriptionPlan::Monthly
                ? $now->modify('+1 month')
                : $now->modify('+1 year');

            $subscription = new Subscription();
            $subscription->setUser($this->getUser());
            $subscription->setPlan($plan);
            $subscription->setAmount((string)$amount);
            $subscription->setStatus('active');
            $subscription->setStartsAt($now);
            $subscription->setExpiresAt($expires);
            $subscription->setPaymentMethod('credit_card');
            $subscription->setCardLast4($last4);

            $em->persist($subscription);
            $em->flush();
            $this->addFlash('success', 'Subscribed successfully! Enjoy premium features.');
            $notifier->notify($this->getUser(), sprintf(
                "Bonjour %s,\n\nVotre abonnement UNIDAR %s est maintenant actif ✅\nValide jusqu'au %s.\n\nProfitez de toutes les fonctionnalités premium : messagerie, génération de contrats et bien plus.\n\n— L'équipe UNIDAR",
                $this->getUser()->getFullName(),
                ucfirst($plan->value),
                $expires->format('d/m/Y')
            ));
            $mailer->sendSubscriptionConfirmation($this->getUser(), $subscription);
        } else {
            $this->addFlash('error', 'Payment failed: ' . $result['message']);
            $notifier->notify($this->getUser(), sprintf(
                "Bonjour %s,\n\nLe paiement de votre abonnement UNIDAR a échoué ❌\nMotif : %s\n\nVeuillez réessayer avec un autre moyen de paiement.\n\n— L'équipe UNIDAR",
                $this->getUser()->getFullName(),
                $result['message'] ?? 'Erreur de paiement'
            ));
            $mailer->sendSubscriptionFailed($this->getUser(), $plan->value, $result['message'] ?? '');
        }

        return $this->redirectToRoute('subscription_index');
    }
}
