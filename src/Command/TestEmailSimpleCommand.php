<?php

namespace App\Command;

use App\Entity\Order;
use App\Entity\Transaction;
use App\Entity\User;
use App\Event\OrderCreatedEvent;
use App\Event\OrderConfirmedEvent;
use App\Event\TransactionCreatedEvent;
use App\Event\TransactionConfirmedEvent;
use App\Event\TransactionFailedEvent;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
    name: 'app:test:email-simple',
    description: 'Test email system with mock data (no database required)',
)]
class TestEmailSimpleCommand extends Command
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Test du système d\'emails avec des données fictives');

        // Créer des objets fictifs pour les tests
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstname('John');
        $user->setName('Doe');

        // Créer une devise fictive
        $currency = new \App\Entity\Currency();
        $currency->setMin('EUR'); // Utilise setMin au lieu de setCode
        $currency->setName('Euro');

        $order = new Order();
        $order->setUser($user);
        $order->setAmount(100.00);
        $order->setStatus('pending');
        $order->setReference('TEST-' . uniqid());
        $order->setCurrency($currency);

        $transaction = new Transaction();
        $transaction->setCommand($order);
        $transaction->setAmount(100.00);
        $transaction->setStatus('pending');
        $transaction->setReference('TXN-' . uniqid());
        $transaction->setCurrency($currency);

        $io->section('Test des événements de commande');

        try {
            $io->text('Dispatch OrderCreatedEvent...');
            $this->eventDispatcher->dispatch(new OrderCreatedEvent($order));
            $io->success('✓ OrderCreatedEvent dispatché avec succès');

            $io->text('Dispatch OrderConfirmedEvent...');
            $this->eventDispatcher->dispatch(new OrderConfirmedEvent($order));
            $io->success('✓ OrderConfirmedEvent dispatché avec succès');
        } catch (\Exception $e) {
            $io->error('Erreur lors du dispatch des événements de commande: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->section('Test des événements de transaction');

        try {
            $io->text('Dispatch TransactionCreatedEvent...');
            $this->eventDispatcher->dispatch(new TransactionCreatedEvent($transaction));
            $io->success('✓ TransactionCreatedEvent dispatché avec succès');

            $io->text('Dispatch TransactionConfirmedEvent...');
            $this->eventDispatcher->dispatch(new TransactionConfirmedEvent($transaction));
            $io->success('✓ TransactionConfirmedEvent dispatché avec succès');

            $io->text('Dispatch TransactionFailedEvent...');
            $this->eventDispatcher->dispatch(new TransactionFailedEvent($transaction));
            $io->success('✓ TransactionFailedEvent dispatché avec succès');
        } catch (\Exception $e) {
            $io->error('Erreur lors du dispatch des événements de transaction: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->success('Tous les événements ont été dispatchés avec succès !');
        $io->note([
            'Les événements ont été traités par les subscribers.',
            'Vérifiez vos logs d\'emails ou votre système de mail pour confirmer l\'envoi des emails.',
            'Si vous utilisez un environnement de développement, les emails peuvent être interceptés par un outil comme MailCatcher.'
        ]);

        return Command::SUCCESS;
    }
}