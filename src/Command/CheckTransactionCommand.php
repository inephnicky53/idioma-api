<?php

namespace App\Command;

use App\Service\CheckTransaction;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check:transaction',
    description: 'Command for check all waiting transactions',
)]
class CheckTransactionCommand extends Command
{

    private CheckTransaction $checkTransaction;

    public function __construct(CheckTransaction $checkTransaction)
    {
        parent::__construct();
        $this->checkTransaction = $checkTransaction;
    }
    protected function configure(): void
    {
        $this
            ->addArgument('status', InputArgument::OPTIONAL, 'For all data in transaction')
            ->addArgument('max', InputArgument::OPTIONAL, 'For size data in transaction')
            ->addOption('all', "a", InputOption::VALUE_NONE, 'For all data in transaction')
            ->addOption('sms', "s", InputOption::VALUE_NONE, 'If sms is send in transaction');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $status = $input->getArgument('status');
        $max = $input->getArgument('max');

        /*if ($arg1) {
            $io->note(sprintf('You passed an argument: %s', $arg1));
        }*/
        $all = $input->getOption('all');
        //dd($all, $input->getOption('sms'), $status, $max);
        if ($all) {
            $i = $this->checkTransaction->allWaitings($status, $input->getOption('sms'), $max);
        } else
            $i = $this->checkTransaction->all();


        $io->success($i . ' transactions sont mit à jour.');

        return Command::SUCCESS;
    }
}
