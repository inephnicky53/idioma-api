<?php

namespace App\Service;

use App\Entity\Transaction;
use App\Entity\UserCourse;
use App\Entity\UserTeacher;
use App\Exception\PaymentException;
use App\Idioma;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use function Symfony\Component\Translation\t;

class OperatorProcess
{

    const PAYIN_BNK = 'PAYIN-BNK';
    const PAYIN_MOB = 'PAYIN-MOB';
    const PAYMENT = [
        //'Banque' => self::PAYIN_BNK,
        'Mobile' => self::PAYIN_MOB,
    ];


    /**
     * @var Transaction
     */
    public $transaction;
    private $manager;
    private $options = [
        'currency' => "USD",
        'merchant_phone' => 243899536100,
        'merchant_name' => 'ECOSYS',
        'type' => 1
    ];
    /**
     * @var RouterInterface
     */
    private $router;
    private $flexPayToken = "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOiI0MkkydjNXQkhUUVdpTlg4ejhQVSIsInJvbGVzIjpbIk1FUkNIQU5UIl0sImlzcyI6Ii9sb2dpbiIsImV4cCI6MTczNTY4NjAwMH0.b3H5IvM1cNtQ5I3Xz3Rf3hBO_pbgFgQ5VpdKrFUI3g0";

    public function __construct(
        EntityManagerInterface $manager,
        RouterInterface $router = null
    )
    {
        $this->manager = $manager;
        $this->router = $router;
    }

    public function checkBalance($demande_type, $demande): Transaction
    {
        $callbackUrl = $this->router->generate('callback_flexpay', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $cost = $this->config->getCost($demande_type, $this->options['currency']);

        $transaction = new Transaction();
        $transaction->setCreatedAt(new DateTimeImmutable());
        $transaction->setOperator($this->options['operator']);
        $transaction->setPhone($this->options['phone']);
        $transaction->setAmount($cost);
        $transaction->setCurrency($this->options['currency']);
        $transaction->setCallback($callbackUrl);
        $request = [
            "merchant" => $this->options['merchant_name'],
            "type" => 1,
            "phone" => $transaction->getPhone(),
            "reference" => $transaction->getRef(),
            "amount" => $transaction->getCost(),
            "currency" => $transaction->getCurrency(),
            "callbackUrl" => $callbackUrl,
        ];

        $ch = curl_init($this->getFlexpayRemoteEndepoint() . '/paymentService');
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type:application/json',
            sprintf('Authorization: Bearer %s', $this->flexPayToken)
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $data = json_decode($response, true);

            $transaction->setProviderReference($data['orderNumber']);
            if ($data['code'] === "0") {
                $transaction->setStatus(Idioma::STATUS_PROCESS);
                $transaction->setMessage($data['message']); //info
            } else {
                $transaction->setMessage($data['message']);
                $transaction->setStatus(Idioma::STATUS_ERROR);
            }

        } else {
            $transaction->setStatus(Idioma::STATUS_ERROR);
            $transaction->setMessage('Connection error');
        }
        $transaction->setResponsedAt(new DateTimeImmutable());
        $this->transaction = $transaction;
        $this->manager->persist($transaction);
        $this->manager->flush();

        return $transaction;
    }

    public function approuveOrder(Transaction $transaction)
    {
        //$orderRepository = $this->manager->getRepository(Order::class);
        $order = $transaction->getCommand();
        $order->setStatus(Idioma::STATUS_SUCCESS);

        $order->getUserCourses()->map(function (UserCourse $course) {
            $course
                ->setIsBuyed(true)
                ->setBuyedAt(new DateTimeImmutable());
        });

        $order->getTeachers()->map(function (UserTeacher $userTeacher) {
            $userTeacher
                ->addHours(1)
                ->setBuyedAt(new DateTimeImmutable());
        });

        //$this->manager->persist($order);
        $this->manager->flush();

    }

    public function desapprouveOrder(Transaction $transaction)
    {
        //$orderRepository = $this->manager->getRepository(Order::class);
        $order = $transaction->getCommand();
        $order->setStatus(Idioma::STATUS_FAILED);

        $this->manager->persist($order);
        $this->manager->flush();
    }


    public function sendError($message = null): string
    {
        return $message ?? $this->config->getResultatMessageFailed();
    }

    public function setOptions(array $options)
    {
        $this->options = array_merge($this->options, $options);
    }

    public function flexPayProcess(Transaction $transaction, $type)
    {

        $request = [
            "merchant" => $this->options['merchant_name'],
            "type" => $type,
            "phone" => $transaction->getPhone(),
            "reference" => $transaction->getReference(),
            "amount" => $transaction->getAmount(),
            "currency" => $transaction->getCurrency()->getMin(),
            "callbackUrl" => "https://api.idioma.international",
        ];
        $ch = curl_init($this->getFlexpayRemoteEndepoint() . '/paymentService');
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type:application/json',
            sprintf('Authorization: Bearer %s', $this->flexPayToken)
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            return json_decode($response, true);
        }
        return false;

        /*if ($response) {
            $data = json_decode($response, true);
            $transaction->setProviderReference($data['orderNumber']);
            if ($data['code'] === "0") {
                $transaction->setStatus(Transaction::STATUS_WAIT);
                $transaction->setMessage($data['message']); //info
            } else {
                $transaction->setMessage($data['message']);
                $transaction->setStatus(Transaction::STATUS_ERROR);
            }

        } else {
            $transaction->setStatus(Transaction::STATUS_ERROR);
            $transaction->setMessage('Connection error');
        }

        $this->manager->persist($transaction);
        $this->manager->flush();
        return $transaction;*/
    }

    private function getFlexpayRemoteEndepoint()
    {
        return 'https://backend.flexpay.cd/api/rest/v1';
    }

    /**
     * @param Transaction $transaction
     * @return Transaction
     * @throws PaymentException
     */
    public function process(Transaction $transaction)
    {
        if (isset($this->options['operator'])) {
            switch ($this->options['operator']) {
                case self::PAYMENT['Mobile']:
                    return $this->flexPayProcess($transaction, 1);
                case self::PAYMENT['Banque']:
                    return $this->flexPayProcess($transaction, 2);
                default :
                    throw new PaymentException("La méthode de paiement n'existe pas");
            }
        }
        throw new PaymentException('La méthode de paiement manquante');
    }

    public function check(Transaction $transaction)
    {
        $ch = curl_init($this->getFlexpayRemoteEndepoint() . '/check/' . $transaction->getProviderReference());
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type:application/json',
            sprintf('Authorization: Bearer %s', $this->flexPayToken)
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            return json_decode($response, true);
        }

        return false;
    }
}
