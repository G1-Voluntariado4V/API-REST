<?php

namespace App\Tests\Integration;

use App\Entity\Usuario;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RepositoryIntegrationTest extends KernelTestCase
{
    private $entityManager;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    // Verifica que Doctrine conecta correctamente con la BBDD REAL de test
    public function testConexionBaseDatos(): void
    {
        $conn = $this->entityManager->getConnection();
        // Intentamos una query simple y verificamos que devuelve algo (1)
        $result = $conn->executeQuery('SELECT 1')->fetchOne();
        $this->assertEquals(1, $result);
    }

    // Verifica que podemos leer entidades (Integración ORM <-> SQL Server)
    public function testBuscarUsuarioPorEmail(): void
    {
        // Buscamos un usuario que sabemos que podría no existir, pero probamos que la query no falla
        $repo = $this->entityManager->getRepository(Usuario::class);
        $usuario = $repo->findOneBy(['correo' => 'test_integration@example.com']);

        // No importa si es null o no, lo importante es que no lance excepción SQL
        $this->assertNull($usuario); 
    }

    // Si tuviéramos acceso a querys nativas de Vistas, irían aquí
    public function testEjecutarQueryNativa(): void
    {
        $conn = $this->entityManager->getConnection();
        // Probamos una query simple para asegurar que el driver SQLSrv responde
        $sql = 'SELECT @@VERSION';
        $stmt = $conn->executeQuery($sql);
        $result = $stmt->fetchOne();
        
        $this->assertNotEmpty($result);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Cerrar conexión para evitar fugas de memoria
        if ($this->entityManager) {
            $this->entityManager->close();
            $this->entityManager = null;
        }
    }
}
