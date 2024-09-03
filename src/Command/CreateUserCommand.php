<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create:user',
    description: 'Create a new user in the application',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('firstname', InputArgument::REQUIRED, "user's firstname")
            ->addArgument('email', InputArgument::REQUIRED, "user's email")
            ->addArgument('phone', InputArgument::REQUIRED, "user's phone")
            ->addArgument('pwd', InputArgument::REQUIRED, "user's password")
            ->addOption('admin', 'a', null, "defini en tant qu'admin")
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $firstname = $input->getArgument('firstname');
        $email = $input->getArgument('email');
        $pwd = $input->getArgument('pwd');
        $phone = $input->getArgument('phone');

        $u = new User();
        $u->setFirstname($firstname);
        $u->setEmail($email);
        $u->setPhone($phone);
        $u->setPassword($this->hasher->hashPassword($u, $pwd));
        $u->setIsActive(true);


        if ($input->getOption('admin')) {
            $u->setRoles(['ROLE_ADMIN']);
            $u->setIsVerified(true);
        }

        try {
            $this->em->persist($u);
            $this->em->flush();

            $io->success(sprintf('%s created successfully', $email));
        } catch (\Exception $e) {
            $io->error($e->getMessage());
        }

        return Command::SUCCESS;
    }
}
