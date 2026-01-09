<?php

namespace App\Tests\Integration;

use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test de integración para verificar conexión a BBDD y Repositorios
 */
class RepositoryIntegrationTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    public function testConexionBaseDeDatos(): void
    {
        // Verificar que podemos obtener la conexión y hacer ping
        $conn = $this->entityManager->getConnection();

        // Ejecutar una consulta simple para verificar la conexión
        // connect() es protegido en versiones recientes de DBAL
        $conn->executeQuery('SELECT 1');

        $this->assertTrue($conn->isConnected());
    }

    public function testBuscarUsuario(): void
    {
        // Intentar buscar usuarios (asumiendo que la tabla existe)
        $repo = $this->entityManager->getRepository(Usuario::class);
        $usuarios = $repo->findAll();

        $this->assertIsArray($usuarios);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
        $this->entityManager = null;
    }
}
