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

#[Route('/api', name: 'api_')]
final class OrganizacionController extends AbstractController
{
    #[Route('/organizaciones', name: 'registro_organizacion', methods: ['POST'])]
    public function registrar(
        Request $request,
        EntityManagerInterface $entityManager,
        RolRepository $rolRepository
    ): JsonResponse {
        // 1. Decodificar el JSON recibido del Frontend
        $data = json_decode($request->getContent(), true);

        // 2. Validaciones mínimas (Campos obligatorios)
        // El CIF es vital para identificar a la empresa
        if (!isset($data['google_id'], $data['correo'], $data['nombre'], $data['cif'])) {
            return $this->json(['error' => 'Faltan datos obligatorios (google_id, correo, nombre, cif)'], 400);
        }

        // 3. Iniciar Transacción (Seguridad de Datos)
        // Si falla algo, no se crea ni el usuario ni la organización
        $entityManager->beginTransaction();

        try {
            // ======================================================
            // PASO A: Crear el USUARIO (Login)
            // ======================================================
            $usuario = new Usuario();
            $usuario->setCorreo($data['correo']);
            $usuario->setGoogleId($data['google_id']);

            // Asignar ROL 3 = ORGANIZACIÓN (Según tu script SQL maestro)
            $rolOrg = $rolRepository->find(3);
            if (!$rolOrg) {
                throw new \Exception("El rol Organización (ID 3) no existe en BBDD. Ejecuta los inserts maestros.");
            }
            $usuario->setRol($rolOrg);

            // Guardamos usuario para generar su ID
            $entityManager->persist($usuario);
            $entityManager->flush();

            // ======================================================
            // PASO B: Crear el PERFIL DE ORGANIZACIÓN
            // ======================================================
            $org = new Organizacion();

            // Vinculación ID: La organización hereda el ID del usuario
            $org->setUsuario($usuario);

            // Datos Obligatorios
            $org->setNombre($data['nombre']); // Nombre comercial
            $org->setCif($data['cif']);       // Identificador fiscal

            // Datos Opcionales (usamos ?? null por si no vienen)
            $org->setDescripcion($data['descripcion'] ?? null);
            $org->setDireccion($data['direccion'] ?? null);
            $org->setSitioWeb($data['sitio_web'] ?? null);
            $org->setTelefono($data['telefono'] ?? null);

            // Imagen de perfil (URL de Firebase)
            $org->setImgPerfil($data['img_perfil'] ?? null);

            $entityManager->persist($org);
            $entityManager->flush();

            // ======================================================
            // PASO C: Confirmar (Commit)
            // ======================================================
            $entityManager->commit();

            return $this->json([
                'mensaje' => 'Organización registrada correctamente',
                'id_usuario' => $usuario->getId(),
                'nombre_organizacion' => $org->getNombre()
            ], 201);
        } catch (\Exception $e) {
            // ¡ERROR! Deshacemos todo
            $entityManager->rollback();
            return $this->json(['error' => 'Error al registrar: ' . $e->getMessage()], 500);
        }
    }
}
