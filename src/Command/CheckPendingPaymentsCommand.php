<?php

namespace App\Command;

use App\Service\CheckTransaction;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Re-vérifie auprès de FlexPay tous les paiements restés en `WAIT`.
 *
 * PaymentManager::createFromInput() borne son attente synchrone (~45 s) pour
 * ne pas bloquer indéfiniment la requête HTTP : au-delà, le client continue
 * à sonder depuis le frontend, mais un onglet fermé ou une session perdue
 * laisserait le paiement orphelin (jamais résolu) sans cette commande — la
 * seule alternative jusqu'ici était le bouton manuel du back-office admin.
 */
#[AsCommand(
    name: 'app:payments:check-pending',
    description: 'Revérifie auprès de FlexPay les paiements restés en attente',
)]
final class CheckPendingPaymentsCommand extends Command
{
    public function __construct(
        private readonly CheckTransaction $checkTransaction,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = $this->checkTransaction->checkAllPendingPayments();

        $io->success(sprintf('%d paiement(s) en attente revérifié(s).', $count));

        return Command::SUCCESS;
    }
}
