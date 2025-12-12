<?php

namespace App\Command;

use App\Entity\Rol;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-admin-user',
    description: 'Creates the admin user manually.',
)]
class CreateAdminUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $googleId = '117993083263588915982';
        $email = 'jr.rojas0327@gmail.com';
        $roleName = 'Coordinador';

        // 1. Buscar o Crear Rol Coordinador
        $rol = $this->entityManager->getRepository(Rol::class)->findOneBy(['nombre' => $roleName]);
        
        if (!$rol) {
            $io->note("El rol '$roleName' no existe. Creándolo...");
            $rol = new Rol();
            $rol->setNombre($roleName);
            $this->entityManager->persist($rol);
            $this->entityManager->flush(); // Guardar para obtener ID
        }

        // 2. Crear Usuario
        // Verificar si ya existe
        $existing = $this->entityManager->getRepository(Usuario::class)->findOneBy(['googleId' => $googleId]);
        if ($existing) {
            $io->warning("El usuario con Google ID $googleId ya existe.");
            return Command::FAILURE;
        }

        $usuario = new Usuario();
        $usuario->setGoogleId($googleId);
        $usuario->setCorreo($email);
        $usuario->setRol($rol);
        $usuario->setEstadoCuenta('Activa');
        $usuario->setFechaRegistro(new \DateTime());

        $this->entityManager->persist($usuario);
        $this->entityManager->flush();

        $io->success("Usuario Coordinador creado exitosamente:\nEmail: $email\nGoogle ID: $googleId");

        return Command::SUCCESS;
    }
}
