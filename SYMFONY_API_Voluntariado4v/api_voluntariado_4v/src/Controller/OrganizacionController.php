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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/api', name: 'api_')]
final class OrganizacionController extends AbstractController
{
    #[Route('/organizaciones', name: 'registro_organizacion', methods: ['POST'])]
    public function registrar(
        Request $request,
        EntityManagerInterface $entityManager,
        RolRepository $rolRepository,
        UserPasswordHasherInterface $hasher // 2. IMPORTANTE: Inyectar el hasher aquí
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['google_id'], $data['correo'], $data['nombre'], $data['cif'])) {
            return $this->json(['error' => 'Faltan datos obligatorios'], 400);
        }

        $entityManager->beginTransaction();

        try {
            // --- CREAR USUARIO ---
            $usuario = new Usuario();
            $usuario->setCorreo($data['correo']);
            $usuario->setGoogleId($data['google_id']);

            // 3. CORRECCIÓN CRÍTICA: Rellenar campos obligatorios de BBDD
            $usuario->setEstadoCuenta('Pendiente');

            // Generamos contraseña aleatoria y la hasheamos
            $randomPassword = bin2hex(random_bytes(10));
            $usuario->setPassword($hasher->hashPassword($usuario, $randomPassword));
            // -----------------------------------------------------------

            $rolOrg = $rolRepository->find(3); // ID 3 = Organización
            if (!$rolOrg) throw new \Exception("Rol Organización no encontrado");

            $usuario->setRol($rolOrg);

            $entityManager->persist($usuario);
            $entityManager->flush();

            // --- CREAR ORGANIZACIÓN ---
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

            // Usamos los Groups para devolver todo limpio
            return $this->json($org, 201, [], ['groups' => 'usuario:read']);
        } catch (\Exception $e) {
            $entityManager->rollback();
            return $this->json(['error' => 'Error al registrar: ' . $e->getMessage()], 500);
        }
    }

    // ======================================================
    // ACTUALIZAR ORGANIZACIÓN (PUT)
    // ======================================================
    #[Route('/organizaciones/{id}', name: 'actualizar_organizacion', methods: ['PUT'])]
    public function actualizar(
        int $id,
        Request $request,
        UsuarioRepository $usuarioRepo,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        // 1. Buscar Usuario
        $usuario = $usuarioRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], 404);

        // 2. Buscar Perfil Organización
        $org = $entityManager->getRepository(Organizacion::class)->findOneBy(['usuario' => $usuario]);
        if (!$org) return $this->json(['error' => 'Perfil de organización no encontrado'], 404);

        // 3. Actualizar datos
        $data = json_decode($request->getContent(), true);

        // Solo actualizamos si el dato viene en el JSON (usamos ?? $valorActual no sirve aquí 
        // porque si no viene, queremos mantener el que estaba. Usamos if isset).

        if (isset($data['nombre'])) $org->setNombre($data['nombre']);
        if (isset($data['descripcion'])) $org->setDescripcion($data['descripcion']);
        if (isset($data['direccion'])) $org->setDireccion($data['direccion']);
        if (isset($data['sitio_web'])) $org->setSitioWeb($data['sitio_web']);
        if (isset($data['telefono'])) $org->setTelefono($data['telefono']);
        if (isset($data['img_perfil'])) $org->setImgPerfil($data['img_perfil']);

        // El CIF normalmente no se permite cambiar tan fácil por temas legales/fiscales, 
        // pero si quieres permitirlo:
        if (isset($data['cif'])) $org->setCif($data['cif']);

        $org->setUpdatedAt(new \DateTime());

        $entityManager->flush();

        return $this->json($org, 200, [], ['groups' => 'usuario:read']);
    }

    // ======================================================
    // ELIMINAR ORGANIZACIÓN (SOFT DELETE)
    // ======================================================
    #[Route('/organizaciones/{id}', name: 'borrar_organizacion', methods: ['DELETE'])]
    public function eliminar(
        int $id,
        UsuarioRepository $usuarioRepo,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $usuario = $usuarioRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], 404);

        // Soft Delete: Marcamos fecha de borrado y bloqueamos cuenta
        $usuario->setDeletedAt(new \DateTimeImmutable());
        $usuario->setEstadoCuenta('Bloqueada');

        $entityManager->flush();

        return $this->json(['mensaje' => 'Organización eliminada correctamente (Soft Delete)'], 200);
    }
}
