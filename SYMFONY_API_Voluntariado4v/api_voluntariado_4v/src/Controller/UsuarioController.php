<?php

namespace App\Controller;

use App\Entity\Usuario;
use App\Repository\RolRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;


#[Route('/api', name: 'api_')]
final class UsuarioController extends AbstractController
{
    #[Route('/usuario', name: 'crear_usuario', methods: ['POST'])]
    public function crear(
        Request $request,
        EntityManagerInterface $entityManager,
        RolRepository $rolRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // 1. Validar datos
        if (!isset($data['correo']) || !isset($data['google_id']) || !isset($data['id_rol'])) {
            return $this->json(['error' => 'Faltan datos obligatorios'], 400);
        }

        // 2. Buscar el Rol (Para la FK)
        $rol = $rolRepository->find($data['id_rol']);
        if (!$rol) {
            return $this->json(['error' => 'Rol no encontrado'], 404);
        }

        // 3. Crear Usuario
        $usuario = new Usuario();
        $usuario->setCorreo($data['correo']);
        $usuario->setGoogleId($data['google_id']);
        $usuario->setRol($rol); // Pasamos el OBJETO Rol
        // fechaRegistro y estadoCuenta se ponen solos en el __construct de la Entidad

        try {
            $entityManager->persist($usuario);
            $entityManager->flush();
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al guardar: ' . $e->getMessage()], 500);
        }

        return $this->json([
            'mensaje' => 'Usuario creado',
            'id' => $usuario->getId(),
            'correo' => $usuario->getCorreo()
        ], 201);
    }
}
