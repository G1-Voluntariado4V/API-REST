<?php

namespace App\Controller;

use App\Entity\Coordinador;
use App\Entity\Usuario;
use App\Repository\RolRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
final class CoordinadorController extends AbstractController
{
    #[Route('/coordinadores', name: 'registro_coordinador', methods: ['POST'])]
    public function registrar(
        Request $request,
        EntityManagerInterface $entityManager,
        RolRepository $rolRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // 1. Validaciones
        if (!isset($data['google_id'], $data['correo'], $data['nombre'])) {
            return $this->json(['error' => 'Faltan datos obligatorios'], 400);
        }

        $entityManager->beginTransaction();
        try {
            // A. Crear USUARIO
            $usuario = new Usuario();
            $usuario->setCorreo($data['correo']);
            $usuario->setGoogleId($data['google_id']);

            // BUSCAR ROL DE COORDINADOR (ID 4 según tu esquema)
            // Ojo: Asegúrate de que en tu BBDD el ID 4 es Coordinador/Admin
            $rolCoord = $rolRepository->find(4);

            if (!$rolCoord) {
                // Intento de recuperación por nombre si el ID falla
                $rolCoord = $rolRepository->findOneBy(['nombre' => 'Coordinador']);
            }

            if (!$rolCoord) throw new \Exception("El rol Coordinador no existe en BBDD");

            $usuario->setRol($rolCoord);
            $usuario->setEstadoCuenta('Activa'); // Los admins nacen activos

            $entityManager->persist($usuario);
            $entityManager->flush();

            // B. Crear PERFIL COORDINADOR
            $coord = new Coordinador();
            $coord->setUsuario($usuario);
            $coord->setNombre($data['nombre']);
            $coord->setApellidos($data['apellidos'] ?? null);
            $coord->setTelefono($data['telefono'] ?? null);

            // Fecha de actualización automática
            $coord->setUpdatedAt(new \DateTime());

            $entityManager->persist($coord);
            $entityManager->flush();

            $entityManager->commit();

            return $this->json([
                'mensaje' => 'Coordinador registrado correctamente',
                'id_usuario' => $usuario->getId(),
                'rol' => 'Coordinador'
            ], 201);
        } catch (\Exception $e) {
            $entityManager->rollback();

            // Control de duplicados
            if (str_contains($e->getMessage(), 'Duplicate')) {
                return $this->json(['error' => 'El usuario ya existe'], 409);
            }

            return $this->json(['error' => 'Error al registrar: ' . $e->getMessage()], 500);
        }
    }
}
