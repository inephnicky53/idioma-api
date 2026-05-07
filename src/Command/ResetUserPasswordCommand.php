<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:reset-password',
    description: 'Reset a user password by email',
    hidden: false,
)]
class ResetUserPasswordCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'User email')
            ->addOption('password', 'p', InputOption::VALUE_OPTIONAL, 'New password (if not provided, will be prompted)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = $input->getArgument('email');
        
        if (!$email) {
            $helper = $this->getHelper('question');
            $question = new Question('Please enter the user email: ');
            $email = $helper->ask($input, $output, $question);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $output->writeln("<error>User with email '{$email}' not found</error>");
            return Command::FAILURE;
        }

        $password = $input->getOption('password');
        
        if (!$password) {
            $helper = $this->getHelper('question');
            $question = new Question('Please enter the new password: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = $helper->ask($input, $output, $question);
        }

        if (strlen($password) < 6) {
            $output->writeln("<error>Password must be at least 6 characters long</error>");
            return Command::FAILURE;
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $output->writeln("<info>Password for user '{$email}' has been reset successfully</info>");
        return Command::SUCCESS;
    }
}

