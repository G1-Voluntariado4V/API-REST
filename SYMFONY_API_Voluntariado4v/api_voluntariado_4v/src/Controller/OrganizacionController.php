<?php

namespace App\Controller;

use App\Entity\Organizacion;
use App\Entity\Usuario;
use App\Repository\RolRepository;
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
}
