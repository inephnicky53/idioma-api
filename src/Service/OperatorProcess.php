<?php

namespace App\Service;

use App\Entity\Payment;
use App\Entity\Subscription;
use App\Enum\Currency;

use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;

use App\Enum\TransactionStatus;
use App\Enum\TransactionType;
use App\Exception\PaymentException;
use App\Service\Payment\FlexPayProvider;
use App\Service\Payment\PaymentProviderInterface;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class OperatorProcess
{

    public $transaction;
    private array $options = [
        'currency' => Currency::USD,
    ];

    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly RouterInterface        $router,
        private readonly FlexPayProvider        $flexPayProvider,
        private readonly string                 $provider,
    )
    {
    }

    /**
     * @throws PaymentException
     *
    public function createTransaction(TransactionType $demande_type, $demande): Payment
    {
        $provider = $this->getProvider();
        return $provider->createTransaction($demande, 1, array_merge($this->options, [
            'demande_type' => $demande_type
        ]));
    }*/

    /**
     * @throws PaymentException
     */
    private function getProvider(): PaymentProviderInterface
    {
        return match ($this->provider) {
            'flexpay' => $this->flexPayProvider,
            default => throw new PaymentException("Provider non supporté: " . $this->options['provider'])
        };
    }

    /**
     * @throws PaymentException
     */
    public function process(Subscription $candidate): Payment
    {
        if (!isset($this->options['operator'])) {
            throw new PaymentException('La méthode de paiement manquante');
        }

        $provider = $this->getProvider();

        $type = match ($this->options['operator']) {
            PaymentMethod::MOBILE => 1,
            PaymentMethod::BANK => 2,
            default => throw new PaymentException("La méthode de paiement n'existe pas"),
        };

        return $provider->createTransaction($candidate, $type, $this->options);
    }

    /**
     * @throws PaymentException
     */
    public function check(Payment $payment): bool|array
    {
        $provider = $this->getProvider();
        return $provider->checkTransaction($payment);
    }

    public function setOptions(array $options): void
    {
        $this->options = array_merge($this->options, $options);
    }

    public function validateSubscription(Payment $payment): void
    {
        $subscription = new Subscription();
        $subscription->setUser($payment->getUser());
        $subscription->setPlan($payment->getSubscriptionPlan());
        $subscription->setStartDate(new DateTime());
        $subscription->setEndDate(new DateTime('+30 days'));
        $subscription->setStatus('active');
        $subscription->setAutoRenew(false);

        if ($demande) {
            $link = $this->palmaresBaseUrl . '?id=' . $demande->getLink();
            $message = $this->config->getPalmaresMessageSuccessReplace($link);
            $demande->setStatus(PaymentStatus::COMPLETED);
        } else {
            $demande->setStatus(PaymentStatus::ERROR);
            $message = $this->sendError();
        }

        $sms = new SMS();
        if ($sms->send($payment->getPhone(), $message) == 201) {
            $payment->setIsSmsSend(true);
        } else {
            $payment->setIsSmsSend(false);
        }

        $this->manager->persist($demande);
        $this->manager->persist($payment);
        $this->manager->flush();
    }

    public function sendConfirmationReleve(Transaction $transaction): void
    {
        $demandeRepository = $this->manager->getRepository(TransactionType::RELEVE->getEntityClass());
        /** @var DocumentRequest $demande */
        $demande = $demandeRepository->find($transaction->getDemandeId());

        $studentRepository = $this->manager->getRepository(Student::class);
        $student = $demande->getStudent() ?? $studentRepository->getByCode($demande->getReference());

        if ($student) {
            $demande->setStatus(PaymentStatus::COMPLETED);
            $demande->setIsFinded(true);
            $demande->setLink(uniqid());
            $link = $this->router->generate('app_transfer_response', [
                'link' => $demande->getLink()
            ], UrlGeneratorInterface::ABSOLUTE_URL);
            $message = $this->config->getReleveMessageSuccessReplace($student, $link);
        } else {
            $demande->setStatus(PaymentStatus::ERROR);
            $message = $this->config->getReleveMessageFailed();
        }

        $sms = new SMS();
        if ($sms->send($transaction->getPhone(), $message) == 201) {
            $transaction->setIsSmsSend(true);
        } else {
            $transaction->setStatus(TransactionStatus::PENDING);
        }

        $this->manager->persist($demande);
        $this->manager->persist($transaction);
        $this->manager->flush();
    }

    public function sendOnProcessDiploma(Transaction $transaction): void
    {
        $demandeRepository = $this->manager->getRepository(DocumentRequest::class);
        $demande = $demandeRepository->find($transaction->getDemandeId());

        $studentRepository = $this->manager->getRepository(Student::class);
        $student = $demande->getStudent() ?? $studentRepository->findOneBy(['reference' => $demande->getReference()]);

        if ($student) {
            $demande->setStatus(PaymentStatus::PROCESS);
            $demande->setStudent($student);
            $demande->setPromotion($student->getPromotion());
            $demande->setYearSchool($student->getYearSchool());
            $demande->setLevel($student->getPromotion()->getLevel());
            $demande->setIsFinded(true);
            $demande->setLink(uniqid());
            $message = $this->config->getDiplomaMessageProcessReplace($student);
        } else {
            $demande->setStatus(PaymentStatus::ERROR);
            $message = $this->config->getDiplomaMessageFailedReplace($demande);
        }

        $sms = new SMS();
        if ($sms->send($transaction->getPhone(), $message) == 201) {
            $transaction->setIsSmsSend(true);
        } else {
            $transaction->setStatus(TransactionStatus::PENDING);
        }

        $this->manager->persist($demande);
        $this->manager->persist($transaction);
        $this->manager->flush();
    }

    public function sendConfirmationCodification(Transaction $transaction): void
    {
        $identificationRepository = $this->manager->getRepository(Identification::class);
        $codification = $identificationRepository->find($transaction->getDemandeId());

        if (!$codification) {
            $transaction->setStatus(TransactionStatus::FAILED);
            $transaction->setMessage("Demande de codification non trouvée");
            $this->manager->persist($transaction);
            $this->manager->flush();
            return;
        }

        $codification->setStatus(PaymentStatus::PROCESS);
        $codification->setIsPaid(true);

        $student = $this->studentService->check($codification);
        if ($student) {
            $codification->setStudent($student);
            $codification->setIsStudentFinded(true);
        }

        $message = $this->config->getCodificationMessageConfirmationReplace($codification);
        $sms = new SMS();
        if ($sms->send($transaction->getPhone(), $message)) {
            $transaction->setIsSmsSend(true);
        } else {
            $transaction->setStatus(TransactionStatus::PENDING);
            $transaction->setMessage("Échec de l'envoi du SMS");
        }

        try {
            $this->manager->persist($codification);
            $this->manager->persist($transaction);
            $this->manager->flush();
        } catch (Exception $e) {
            $transaction->setStatus(TransactionStatus::FAILED);
            $transaction->setMessage('Erreur lors de la sauvegarde: ' . $e->getMessage());
            $this->manager->persist($transaction);
            $this->manager->flush();
        }
    }

    public function sendConfirmationTransfert(Transaction $transaction): void
    {
        $transfertRepository = $this->manager->getRepository(Transfer::class);
        $transfert = $transfertRepository->find($transaction->getDemandeId());
        $transfert->setStatus(TransfertStatus::PROCESS);

        $message = $this->config->getTransferConfirmationMessageReplace($transfert);
        $sms = new SMS();
        if ($sms->send($transfert->getPhone (), $message) == 201) {
            $transaction->setIsSmsSend(true);
        } else {
            $transaction->setStatus(TransactionStatus::PENDING);
        }

        $this->manager->persist($transfert);
        $this->manager->persist($transaction);
        $this->manager->flush();
    }

    public function sendError($message = null): string
    {
        return $message ?? $this->config->getResultatMessageFailed();
    }
}
