<?php

namespace App\Controller;

use App\Entity\Usuario;
use App\Repository\RolRepository;
use App\Repository\UsuarioRepository; // Necesario para el GET
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface; // <--- NUEVO
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
final class UsuarioController extends AbstractController
{
    // ========================================================================
    // MÉTODO NUEVO: LISTAR USUARIOS (GET)
    // ========================================================================
    #[Route('/usuarios', name: 'usuarios_index', methods: ['GET'])]
    public function index(UsuarioRepository $usuarioRepository): JsonResponse
    {
        $usuarios = $usuarioRepository->findAll();
        // Usamos el grupo para que se vea el rol y se oculte la contraseña
        return $this->json($usuarios, 200, [], ['groups' => 'usuario:read']);
    }

    // ========================================================================
    // MÉTODO TUYO ADAPTADO: CREAR USUARIO (POST)
    // ========================================================================
    #[Route('/usuario', name: 'crear_usuario', methods: ['POST'])]
    public function crear(
        Request $request,
        EntityManagerInterface $entityManager,
        RolRepository $rolRepository,
        UserPasswordHasherInterface $hasher // <--- Inyectamos el Hasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // 1. Validar datos básicos
        if (!isset($data['correo']) || !isset($data['google_id']) || !isset($data['id_rol'])) {
            return $this->json(['error' => 'Faltan datos obligatorios (correo, google_id, id_rol)'], 400);
        }

        // 2. Buscar el Rol
        $rol = $rolRepository->find($data['id_rol']);
        if (!$rol) {
            return $this->json(['error' => 'Rol no encontrado'], 404);
        }

        // 3. Crear Usuario
        $usuario = new Usuario();
        $usuario->setCorreo($data['correo']);
        $usuario->setGoogleId($data['google_id']);
        $usuario->setRol($rol);

        // --- CAMBIOS NECESARIOS POR SEGURIDAD ---

        // A. Estado de cuenta (Obligatorio en BBDD)
        $usuario->setEstadoCuenta('Pendiente');

        // B. Contraseña (Obligatoria en BBDD aunque usen Google)
        // Generamos una contraseña aleatoria interna, ya que entran con Google
        $randomPassword = bin2hex(random_bytes(10));
        $usuario->setPassword($hasher->hashPassword($usuario, $randomPassword));

        // ----------------------------------------

        try {
            $entityManager->persist($usuario);
            $entityManager->flush();
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al guardar: ' . $e->getMessage()], 500);
        }

        // Devolvemos el usuario creado usando los Grupos para que quede bonito
        return $this->json($usuario, 201, [], ['groups' => 'usuario:read']);
    }
}
