<?php

namespace App\Controller;

use App\Entity\Inscripcion;
use App\Repository\InscripcionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('', name: 'api_inscripciones_')]
#[OA\Tag(name: 'Gestión Inscripciones', description: 'Endpoints para que Organizaciones y Coordinadores gestionen solicitudes')]
final class InscripcionController extends AbstractController
{
    // ========================================================================
    // 1. VER ASPIRANTES (GET) - Vista para la Organización
    // URL: /api/actividades/{id}/inscripciones
    // ========================================================================
    #[Route('/actividades/{id}/inscripciones', name: 'listar_aspirantes', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Lista de voluntarios inscritos en una actividad',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'id_voluntario', type: 'integer'),
                    new OA\Property(property: 'nombre_completo', type: 'string'),
                    new OA\Property(property: 'fecha_solicitud', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'estado', type: 'string', example: 'Pendiente')
                ]
            )
        )
    )]
    public function listarAspirantes(int $id, InscripcionRepository $repo): JsonResponse
    {
        // Buscamos todas las inscripciones de esa actividad
        $inscripciones = $repo->findBy(['actividad' => $id]);

        $resultado = [];
        foreach ($inscripciones as $ins) {
            $vol = $ins->getVoluntario();
            $resultado[] = [
                'id_voluntario'   => $vol->getUsuario()->getId(), // ID Usuario
                'nombre_completo' => $vol->getNombre() . ' ' . $vol->getApellidos(),
                'fecha_solicitud' => $ins->getFechaSolicitud()->format('Y-m-d H:i:s'),
                'estado'          => $ins->getEstadoSolicitud()
            ];
        }

        return $this->json($resultado, Response::HTTP_OK);
    }

    // ========================================================================
    // 2. CAMBIAR ESTADO (PATCH) - Aceptar/Rechazar/Cancelar
    // URL: /api/actividades/{idActividad}/inscripciones/{idVoluntario}
    // ========================================================================
    #[Route('/actividades/{idActividad}/inscripciones/{idVoluntario}', name: 'cambiar_estado', methods: ['PATCH'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'estado', type: 'string', enum: ['Aceptada', 'Rechazada', 'Cancelada', 'Pendiente'], description: 'Nuevo estado')
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Estado actualizado correctamente')]
    #[OA\Response(response: 409, description: 'Error de negocio (Cupo lleno)')]
    public function cambiarEstado(
        int $idActividad,
        int $idVoluntario,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        // Buscamos la inscripción exacta
        // Doctrine mapea 'actividad' y 'voluntario' automáticamente a los IDs si pasas enteros en findOneBy
        $inscripcion = $em->getRepository(Inscripcion::class)->findOneBy([
            'actividad' => $idActividad,
            'voluntario' => $idVoluntario
        ]);

        if (!$inscripcion) return $this->json(['error' => 'Inscripción no encontrada'], Response::HTTP_NOT_FOUND);

        $data = json_decode($request->getContent(), true);
        $nuevoEstado = $data['estado'] ?? null;

        if (!in_array($nuevoEstado, ['Aceptada', 'Rechazada', 'Cancelada', 'Pendiente'])) {
            return $this->json(['error' => 'Estado inválido. Valores permitidos: Aceptada, Rechazada, Cancelada, Pendiente'], Response::HTTP_BAD_REQUEST);
        }

        $inscripcion->setEstadoSolicitud($nuevoEstado);

        // Asumiendo que añadiste el setter en tu Entidad Inscripcion como te indiqué antes
        if (method_exists($inscripcion, 'setUpdatedAt')) {
            $inscripcion->setUpdatedAt(new \DateTime());
        }

        try {
            // 💥 AQUÍ salta el Trigger TR_Check_Cupo_Update si intentamos aceptar y ya está lleno
            $em->flush();
            return $this->json(['mensaje' => 'Estado actualizado a ' . $nuevoEstado], Response::HTTP_OK);
        } catch (\Exception $e) {
            $msg = $e->getMessage();

            // Captura de errores específicos de SQL Server definidos en tus Triggers
            if (str_contains($msg, 'ERROR DE NEGOCIO') || str_contains($msg, 'ERROR DE CUPO')) {
                return $this->json(['error' => 'No se puede aceptar: El cupo de la actividad se ha completado.'], Response::HTTP_CONFLICT);
            }

            return $this->json(['error' => 'Error al actualizar estado: ' . $msg], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
