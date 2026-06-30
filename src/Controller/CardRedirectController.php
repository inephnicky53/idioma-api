<?php

namespace App\Controller;

use App\Enum\PaymentMethod;
use App\Repository\PaymentRepository;
use App\Service\Payment\FlexPayProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Redirige le navigateur du client vers la page de paiement carte hébergée par
 * FlexPay.
 *
 * L'API carte FlexPay (/v2/pay) du marchand n'expose pas le JSON `{url}`
 * documenté : elle attend un POST `application/x-www-form-urlencoded` et rend
 * directement sa page de saisie de carte. On renvoie donc ici un mini-formulaire
 * auto-soumis vers FlexPay : le jeton marchand reste côté serveur (jamais dans le
 * bundle JS ni dans le JSON d'API), et l'accès est protégé par un nonce à usage
 * unique généré lors de la création du paiement.
 */
class CardRedirectController extends AbstractController
{
    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly FlexPayProvider $flexPay,
        private readonly string $frontendUrl,
    ) {}

    #[Route('/payment/card/{reference}/{nonce}', name: 'flexpay_card_redirect', methods: ['GET'])]
    public function redirectToFlexPay(string $reference, string $nonce): Response
    {
        $payment = $this->payments->findOneBy(['reference' => $reference]);

        if (!$payment || $payment->getPaymentMethod() !== PaymentMethod::CARD) {
            throw new NotFoundHttpException('Paiement introuvable.');
        }

        $expectedNonce = $payment->getData()['cardRedirectNonce'] ?? null;
        if (!is_string($expectedNonce) || !hash_equals($expectedNonce, $nonce)) {
            throw new AccessDeniedHttpException('Lien de paiement invalide.');
        }

        // Paiement déjà résolu : on renvoie directement vers la page de confirmation
        // plutôt que de relancer une saisie de carte.
        if ($payment->getStatus()->isFinal()) {
            $status = $payment->getStatus()->isSuccess() ? 'approved' : 'declined';
            $url = rtrim($this->frontendUrl, '/') . '/payment/confirmation?' . http_build_query([
                'reference' => $reference,
                'status' => $status,
            ]);

            return new RedirectResponse($url);
        }

        $form = $this->flexPay->buildCardFormParams($payment);

        $response = $this->render('payment/flexpay_card_redirect.html.twig', [
            'action' => $form['action'],
            'fields' => $form['fields'],
        ]);
        // Page transitoire contenant le jeton : ne jamais mettre en cache.
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
