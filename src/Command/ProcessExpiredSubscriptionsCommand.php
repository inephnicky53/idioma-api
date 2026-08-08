<?php

namespace App\Command;

use App\Service\SubscriptionExpirationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:subscriptions:process-expired',
    description: 'Expire les abonnements non renouvelés et envoie les notifications',
)]
final class ProcessExpiredSubscriptionsCommand extends Command
{
    public function __construct(
        private readonly SubscriptionExpirationService $expirationService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = $this->expirationService->processExpiredSubscriptions();

        $io->success(sprintf('%d abonnement(s) expiré(s) et notifié(s).', $count));

        return Command::SUCCESS;
    }
}
