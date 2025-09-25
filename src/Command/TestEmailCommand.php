<?php

namespace App\Command;

use App\Entity\Order;
use App\Entity\Transaction;
use App\Event\OrderCreatedEvent;
use App\Event\OrderConfirmedEvent;
use App\Event\TransactionCreatedEvent;
use App\Event\TransactionConfirmedEvent;
use App\Event\TransactionFailedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
    name: 'app:test:email',
    description: 'Test email system with new events',
)]
class TestEmailCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Test du système d\'emails avec les nouveaux événements');

        // Récupérer une commande et une transaction existantes pour les tests
        $order = $this->em->getRepository(Order::class)->findOneBy([]);
        $transaction = $this->em->getRepository(Transaction::class)->findOneBy([]);

        if (!$order) {
            $io->error('Aucune commande trouvée dans la base de données pour les tests.');
            return Command::FAILURE;
        }

        if (!$transaction) {
            $io->error('Aucune transaction trouvée dans la base de données pour les tests.');
            return Command::FAILURE;
        }

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
        $io->note('Vérifiez vos logs d\'emails ou votre système de mail pour confirmer l\'envoi des emails.');

        return Command::SUCCESS;
    }
}