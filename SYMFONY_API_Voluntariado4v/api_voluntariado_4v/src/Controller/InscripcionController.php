<?php

namespace App\Controller;

use App\Entity\Inscripcion;
use App\Entity\Actividad;
use App\Entity\Usuario;
// DTOs
use App\Model\Inscripcion\InscripcionResponseDTO;
use App\Model\Inscripcion\InscripcionUpdateDTO;
// Repositorios
use App\Repository\InscripcionRepository;
use App\Repository\ActividadRepository;
// Core
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
// Documentación
use OpenApi\Attributes as OA;
use Nelmio\ApiDocBundle\Attribute\Model;

#[Route('', name: 'api_')]
#[OA\Tag(name: 'Inscripciones (Gestión)', description: 'Gestión de solicitudes por parte de las Organizaciones')]
final class InscripcionController extends AbstractController
{
    // ========================================================================
    // 1. VER SOLICITUDES DE UNA ACTIVIDAD (GET)
    // ========================================================================
    #[Route('/actividades/{idActividad}/inscripciones', name: 'listar_inscripciones_actividad', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Lista de voluntarios inscritos en la actividad',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: InscripcionResponseDTO::class))
        )
    )]
    public function listarSolicitudes(
        int $idActividad,
        ActividadRepository $actRepo,
        EntityManagerInterface $em
    ): JsonResponse {

        $actividad = $actRepo->find($idActividad);
        if (!$actividad) {
            return $this->json(['error' => 'Actividad no encontrada'], Response::HTTP_NOT_FOUND);
        }

        // Obtener todas las inscripciones de la actividad
        $inscripciones = $em->getRepository(Inscripcion::class)->findBy(
            ['actividad' => $actividad],
            ['fechaSolicitud' => 'DESC']
        );

        // Mapear a DTOs
        $dtos = array_map(
            fn(Inscripcion $ins) => InscripcionResponseDTO::fromEntity($ins),
            $inscripciones
        );

        return $this->json($dtos, Response::HTTP_OK);
    }

    // ========================================================================
    // 2. GESTIONAR ESTADO (Aceptar/Rechazar) (PATCH)
    // ========================================================================
    #[Route('/actividades/{idActividad}/inscripciones/{idVoluntario}', name: 'gestionar_inscripcion', methods: ['PATCH'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: new Model(type: InscripcionUpdateDTO::class)
        )
    )]
    #[OA\Response(response: 200, description: 'Estado actualizado correctamente')]
    #[OA\Response(response: 409, description: 'Error de cupo (Overbooking) controlado por Trigger')]
    public function cambiarEstado(
        int $idActividad,
        int $idVoluntario,
        #[MapRequestPayload] InscripcionUpdateDTO $dto,
        EntityManagerInterface $em
    ): JsonResponse {

        // 1. Obtener Inscripción (PK Compuesta)
        $inscripcion = $em->getRepository(Inscripcion::class)->findOneBy([
            'actividad' => $idActividad,
            'voluntario' => $idVoluntario
        ]);

        if (!$inscripcion) {
            return $this->json(['error' => 'Inscripción no encontrada'], Response::HTTP_NOT_FOUND);
        }

        // 2. Aplicar cambio desde el DTO (ya validado automáticamente)
        $inscripcion->setEstadoSolicitud($dto->estado);

        try {
            // El Trigger TR_Check_Cupo_Update saltará aquí si intentamos aceptar y no hay cupo
            $em->flush();

            return $this->json([
                'mensaje' => "Inscripción actualizada a: {$dto->estado}",
                'voluntario' => $idVoluntario,
                'actividad' => $idActividad
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            // Capturamos el error del Trigger de SQL Server
            if (str_contains($e->getMessage(), 'ERROR DE NEGOCIO') || str_contains($e->getMessage(), 'cupo')) {
                return $this->json([
                    'error' => 'No se puede aceptar: El cupo de la actividad está lleno (Overbooking prevented).'
                ], Response::HTTP_CONFLICT);
            }

            return $this->json(['error' => 'Error al actualizar: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
