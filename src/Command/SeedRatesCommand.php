<?php

namespace App\Command;

use App\Entity\Currency;
use App\Entity\Rate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Seeds one exchange rate per currency so cross-currency checkout works.
 *
 * Without a rate for both sides, TransactionManager refuses the order rather
 * than charging a EUR price as the same number of USD — so a fresh install
 * cannot sell anything across currencies until this has run.
 *
 * Idempotent: re-running updates the latest rate of each currency in place
 * instead of stacking duplicates.
 */
#[AsCommand(
    name: 'app:seed:rates',
    description: 'Create or update the exchange rate of each currency (one per currency).',
)]
class SeedRatesCommand extends Command
{
    /**
     * Rate::value = how many units of this currency equal 1 unit of the
     * reference currency. Indicative defaults — confirm them in the admin
     * (Rate CRUD) before taking real payments.
     */
    private const DEFAULT_VALUES = [
        'USD' => 1.0,
        'EUR' => 0.92,
        'CDF' => 2800.0,
    ];

    private const CURRENCY_NAMES = [
        'USD' => 'Dollar américain',
        'EUR' => 'Euro',
        'CDF' => 'Franc congolais',
    ];

    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'reference',
                'r',
                InputOption::VALUE_REQUIRED,
                'Base currency code every rate is expressed against',
                'USD'
            )
            ->addOption(
                'rate',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Override a rate, as CODE:VALUE (e.g. --rate=CDF:2750). Repeatable.'
            )
            ->addOption(
                'create-missing',
                'c',
                InputOption::VALUE_NONE,
                'Also create the Currency row when a code does not exist yet'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $reference = strtoupper($input->getOption('reference'));

        $values = self::DEFAULT_VALUES;
        foreach ($input->getOption('rate') as $pair) {
            if (!str_contains($pair, ':')) {
                $io->error("Invalid --rate value \"$pair\": expected CODE:VALUE.");

                return Command::FAILURE;
            }
            [$code, $value] = explode(':', $pair, 2);
            if (!is_numeric($value) || (float) $value <= 0) {
                $io->error("Invalid rate for $code: \"$value\" must be a positive number.");

                return Command::FAILURE;
            }
            $values[strtoupper($code)] = (float) $value;
        }

        if (!isset($values[$reference])) {
            $io->error("No rate defined for the reference currency $reference.");

            return Command::FAILURE;
        }

        if ($values[$reference] !== 1.0) {
            $io->error("The reference currency $reference must have a rate of 1, got {$values[$reference]}.");

            return Command::FAILURE;
        }

        $currencyRepository = $this->em->getRepository(Currency::class);
        $rateRepository = $this->em->getRepository(Rate::class);
        $rows = [];

        foreach ($values as $code => $value) {
            $currency = $currencyRepository->findOneBy(['min' => $code]);

            if (!$currency) {
                if (!$input->getOption('create-missing')) {
                    $rows[] = [$code, '—', 'skipped (currency absent, use --create-missing)'];
                    continue;
                }

                $currency = (new Currency())
                    ->setMin($code)
                    ->setName(self::CURRENCY_NAMES[$code] ?? $code);
                $this->em->persist($currency);
            }

            // Latest rate wins in RateService (ordered id DESC), so update the
            // most recent row rather than appending a competing one.
            $rate = $rateRepository->findOneBy(['currency' => $currency], ['id' => 'DESC']);
            $action = $rate ? 'updated' : 'created';

            if (!$rate) {
                $rate = (new Rate())->setCurrency($currency);
                $this->em->persist($rate);
            }

            $rate->setReference($reference)->setValue($value);

            $rows[] = [$code, $value, $action];
        }

        $this->em->flush();

        $io->section("Exchange rates (1 $reference = value × currency)");
        $io->table(['Currency', 'Value', 'Action'], $rows);
        $io->warning('Indicative values — confirm them in the admin (Rate) before taking real payments.');

        return Command::SUCCESS;
    }
}
