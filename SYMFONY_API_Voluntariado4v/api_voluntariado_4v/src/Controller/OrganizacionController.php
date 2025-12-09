<?php

namespace App\Controller;

use App\Entity\Organizacion;
use App\Entity\Usuario;
use App\Repository\RolRepository;
use App\Repository\UsuarioRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
final class OrganizacionController extends AbstractController
{
    // [NUEVO] 1. LISTAR (GET) - Vista SQL
    #[Route('/organizaciones', name: 'listar_organizaciones', methods: ['GET'])]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        $conn = $em->getConnection();
        $sql = 'SELECT * FROM VW_Organizaciones_Activas';
        try {
            $listado = $conn->executeQuery($sql)->fetchAllAssociative();
            return $this->json($listado);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al cargar organizaciones'], 500);
        }
    }

    // [MODIFICADO] 2. REGISTRAR (POST) - Sin Hasher
    #[Route('/organizaciones', name: 'registro_organizacion', methods: ['POST'])]
    public function registrar(
        Request $request,
        EntityManagerInterface $entityManager,
        RolRepository $rolRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['google_id'], $data['correo'], $data['nombre'], $data['cif'])) {
            return $this->json(['error' => 'Faltan datos obligatorios'], 400);
        }

        $entityManager->beginTransaction();

        try {
            $usuario = new Usuario();
            $usuario->setCorreo($data['correo']);
            $usuario->setGoogleId($data['google_id']);
            $usuario->setEstadoCuenta('Pendiente'); // Nace pendiente de validación

            $rolOrg = $rolRepository->findOneBy(['nombre' => 'Organizacion']);
            $usuario->setRol($rolOrg);

            $entityManager->persist($usuario);
            $entityManager->flush();

            $org = new Organizacion();
            $org->setUsuario($usuario);
            $org->setNombre($data['nombre']);
            $org->setCif($data['cif']);
            $org->setDescripcion($data['descripcion'] ?? null);
            $org->setDireccion($data['direccion'] ?? null);
            $org->setSitioWeb($data['sitio_web'] ?? null);
            $org->setTelefono($data['telefono'] ?? null);
            $org->setImgPerfil($data['img_perfil'] ?? null);

            $entityManager->persist($org);
            $entityManager->flush();
            $entityManager->commit();

            return $this->json($org, 201, [], ['groups' => 'usuario:read']);
        } catch (\Exception $e) {
            $entityManager->rollback();
            return $this->json(['error' => 'Error al registrar: ' . $e->getMessage()], 500);
        }
    }

    // [NUEVO] 3. VALIDAR (PATCH) - Para que el Admin active la cuenta
    #[Route('/organizaciones/{id}/validar', name: 'validar_organizacion', methods: ['PATCH'])]
    public function validar(int $id, UsuarioRepository $userRepo, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $usuario = $userRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], 404);

        $data = json_decode($request->getContent(), true);
        $nuevoEstado = $data['estado'] ?? 'Activa'; 
        
        $usuario->setEstadoCuenta($nuevoEstado);
        $em->flush();

        return $this->json(['mensaje' => "Estado actualizado a: $nuevoEstado"]);
    }

    // [MANTENIDO] 4. GET ONE
    #[Route('/organizaciones/{id}', name: 'get_organizacion', methods: ['GET'])]
    public function getOne(int $id, UsuarioRepository $userRepo): JsonResponse
    {
        $usuario = $userRepo->find($id);
        if (!$usuario || $usuario->getDeletedAt()) {
            return $this->json(['error' => 'No encontrada'], 404);
        }
        return $this->json($usuario, 200, [], ['groups' => 'usuario:read']);
    }

    // [MANTENIDO] 5. ACTUALIZAR (PUT)
    #[Route('/organizaciones/{id}', name: 'actualizar_organizacion', methods: ['PUT'])]
    public function actualizar(int $id, Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse 
    {
        $usuario = $userRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], 404);

        $org = $em->getRepository(Organizacion::class)->findOneBy(['usuario' => $usuario]);
        if (!$org) return $this->json(['error' => 'Perfil no encontrado'], 404);

        $data = json_decode($request->getContent(), true);

        if (isset($data['nombre'])) $org->setNombre($data['nombre']);
        if (isset($data['descripcion'])) $org->setDescripcion($data['descripcion']);
        if (isset($data['direccion'])) $org->setDireccion($data['direccion']);
        if (isset($data['sitio_web'])) $org->setSitioWeb($data['sitio_web']);
        if (isset($data['telefono'])) $org->setTelefono($data['telefono']);
        if (isset($data['img_perfil'])) $org->setImgPerfil($data['img_perfil']);

        $em->flush();
        return $this->json($org, 200, [], ['groups' => 'usuario:read']);
    }

    // [MANTENIDO] 6. ELIMINAR (DELETE)
    #[Route('/organizaciones/{id}', name: 'borrar_organizacion', methods: ['DELETE'])]
    public function eliminar(int $id, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        $usuario = $userRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], 404);

        $usuario->setDeletedAt(new \DateTimeImmutable());
        $usuario->setEstadoCuenta('Bloqueada');
        $em->flush();

        return $this->json(['mensaje' => 'Organización eliminada correctamente'], 200);
    }
}