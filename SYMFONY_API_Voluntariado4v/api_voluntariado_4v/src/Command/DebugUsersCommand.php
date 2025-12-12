<?php

namespace App\Command;

use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:debug-users',
    description: 'List all users for debugging',
)]
class DebugUsersCommand extends Command
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $users = $this->entityManager->getRepository(Usuario::class)->findAll();

        foreach ($users as $user) {
            $output->writeln("ID: " . $user->getId());
            $output->writeln("Email: " . $user->getCorreo());
            $output->writeln("GoogleID: " . $user->getGoogleId());
            $output->writeln("Rol: " . ($user->getRol() ? $user->getRol()->getNombre() : 'NULL'));
            $output->writeln("-------------------");
        }

        return Command::SUCCESS;
    }
}
