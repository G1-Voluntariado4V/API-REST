<?php

namespace App\Controller;

use App\Entity\Inscripcion;
use App\Entity\Actividad;
use App\Entity\Usuario;
use App\Model\Inscripcion\InscripcionResponseDTO;
use App\Model\Inscripcion\InscripcionUpdateDTO;
use App\Repository\InscripcionRepository;
use App\Repository\ActividadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
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

        $inscripciones = $em->getRepository(Inscripcion::class)->findBy(
            ['actividad' => $actividad],
            ['fechaSolicitud' => 'DESC']
        );

        $dtos = array_map(
            fn(Inscripcion $ins) => InscripcionResponseDTO::fromEntity($ins),
            $inscripciones
        );

        return $this->json($dtos, Response::HTTP_OK);
    }

    // ========================================================================
    // 2. GESTIONAR ESTADO (PATCH)
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
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        // Log entry
        file_put_contents(__DIR__ . '/../../var/debug_patch_entry.log', date('Y-m-d H:i:s') . " - Entrando a cambiarEstado MANUAL: Act:$idActividad, Vol:$idVoluntario\n", FILE_APPEND);

        try {
            $content = json_decode($request->getContent(), true);
            $estado = $content['estado'] ?? null;

            if (!$estado || !in_array($estado, ['Aceptada', 'Rechazada'])) {
                return $this->json(['error' => "Estado inválido o ausente. Recibido: " . print_r($content, true)], Response::HTTP_BAD_REQUEST);
            }

            $actividad = $em->getRepository(Actividad::class)->find($idActividad);
            $voluntario = $em->getRepository(\App\Entity\Voluntario::class)->find($idVoluntario);

            if (!$actividad || !$voluntario) {
                return $this->json(['error' => 'Actividad o Voluntario no encontrado'], Response::HTTP_NOT_FOUND);
            }

            $inscripcion = $em->getRepository(Inscripcion::class)->findOneBy([
                'actividad' => $actividad,
                'voluntario' => $voluntario
            ]);

            if (!$inscripcion) {
                return $this->json(['error' => 'Inscripción no encontrada'], Response::HTTP_NOT_FOUND);
            }

            $inscripcion->setEstadoSolicitud($estado);

            $em->flush();

            return $this->json([
                'mensaje' => "Inscripción actualizada a: {$estado}",
                'voluntario' => $idVoluntario,
                'actividad' => $idActividad
            ], Response::HTTP_OK);

        } catch (\Throwable $e) {
             // Log error para depuracion
             $logContent = date('Y-m-d H:i:s') . " - Error PATCH Inscripcion: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n";
             file_put_contents(__DIR__ . '/../../var/debug_patch_error.log', $logContent, FILE_APPEND);

             if (str_contains($e->getMessage(), 'ERROR DE NEGOCIO') || str_contains($e->getMessage(), 'cupo')) {
                return $this->json([
                    'error' => 'No se puede aceptar: El cupo de la actividad está lleno.'
                ], Response::HTTP_CONFLICT);
            }

            return $this->json(['error' => 'Error interno al actualizar inscripción: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
