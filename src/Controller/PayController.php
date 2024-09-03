<?php

namespace App\Controller;

use App\Entity\Order;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PayController extends AbstractController
{
    #[Route('/orders/new', name: "app_order_new", methods: ['POST'])]
    public function pay(Request $request)
    {
        $token = $request->request->get('authorization');
        dd($token, $request);
        $parse = $this->jwtManager->parse($token);
        if ($parse['exp'] <= (new \DateTimeImmutable())->getTimestamp())
            return null;

        $user = $this->userRepository->find($parse['id']);
        if (is_null($user))
            return null;

        $currency = $request->request->get('currency');
        $items = json_decode($request->request->get('items'));
        $type = $request->request->get('type');
        $destination = $request->request->get('destination');

        $order = new Order();
        $order->setType($type);
        array_map(function ($item) use ($order) {
            $article = $this->articleRepository->findOneBy(['id' => $item->article]);
            $order->addItem(
                (new OrderItem())
                    ->setArticle($article)
                    ->setQuantity($item->quantity)
                    ->setStatus(Codes::STATUS_PENDING)
            );
        }, $items);
        $currency = $this->currencyRepository->findOneBy(["min" => $currency]);
        $order->setCurrency($currency);

        if ($destination) {
            $address = $this->addressRepository->find($destination);
            $order->setDelivery(
                (new Delivery())
                    ->setDestination($address)
            );
        }

        if ($order->getItems()->count() === 0)
            return null;

        $order = $this->orderService->create($order);
        $order->setUser($user);

        if ($order->getType() !== Codes::TYPE_BANK) {
            return $this->redirect('https://tosomba.com/checkout?message="Erreur lors de la commande"');
        }

        $transaction = new Transaction();
        $transaction->setUser($user);
        $transaction->setCommand($order);
        $transaction->setStatus(Codes::STATUS_INIT);
        $transaction->setOperator(OperatorProcess::BANK);

        $data = [
            "access_key" => OperatorProcess::ACCESS_KEY,
            "profile_id" => "drc_tosomba1",
            "transaction_uuid" => $transaction->getReference(),
            "signed_field_names" => "access_key,profile_id,transaction_uuid,signed_field_names,unsigned_field_names,signed_date_time,locale,transaction_type,reference_number,amount,currency",
            "unsigned_field_names" => "",
            "signed_date_time" => gmdate("Y-m-d\TH:i:s\Z"),
            "locale" => "fr",
            "transaction_type" => "authorization",
            "reference_number" => $transaction->getCommand()->getReference(),
            "amount" => intval($transaction->getAmountNormal()),
            "currency" => $transaction->getCurrency()->getMin(),
            "submit" => "Submit",
            "bill_to_forename" => "Paul",
            "bill_to_surname" => "Nganda",
            "bill_to_email" => "paulngandasmith@gmail.com",
            "bill_to_address_line1" => "22 Beni",
            "bill_to_address_city" => "Kinshasa",
            "bill_to_address_postal_code" => 001,
            "bill_to_address_state" => "CD",
            "bill_to_address_country" => "CD",
        ];
        $data['signature'] = $this->operatorProcess->sign($data);

        return $this->render('payment/gateway.html.twig', [
            'url' => $this->operatorProcess->getBankRemoteEndpoint(),
            'data' => $data
        ]);
    }
}
