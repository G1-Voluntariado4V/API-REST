<?php

namespace App\Controller;

use App\Entity\Inscripcion;
use App\Entity\Actividad;
use App\Entity\Usuario;
use App\Repository\InscripcionRepository;
use App\Repository\ActividadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

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
        content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'object'))
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

        // Usamos DQL para traer datos del voluntario optimizados
        $dql = "SELECT i.estadoSolicitud, i.fechaSolicitud, 
                       v.nombre, v.apellidos, v.imgPerfil, u.correo, u.idUsuario as id_voluntario
                FROM App\Entity\Inscripcion i
                JOIN i.voluntario v
                JOIN v.usuario u
                WHERE i.actividad = :actividad
                ORDER BY i.fechaSolicitud DESC";

        $query = $em->createQuery($dql)->setParameter('actividad', $actividad);
        $resultados = $query->getResult();

        return $this->json($resultados, Response::HTTP_OK);
    }

    // ========================================================================
    // 2. GESTIONAR ESTADO (Aceptar/Rechazar) (PATCH)
    // ========================================================================
    #[Route('/actividades/{idActividad}/inscripciones/{idVoluntario}', name: 'gestionar_inscripcion', methods: ['PATCH'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'estado', type: 'string', enum: ['Aceptada', 'Rechazada'], description: 'Nuevo estado')
            ]
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

        // 1. Obtener Inscripción (PK Compuesta)
        $inscripcion = $em->getRepository(Inscripcion::class)->findOneBy([
            'actividad' => $idActividad,
            'voluntario' => $idVoluntario
        ]);

        if (!$inscripcion) {
            return $this->json(['error' => 'Inscripción no encontrada'], Response::HTTP_NOT_FOUND);
        }

        // 2. Leer nuevo estado
        $data = json_decode($request->getContent(), true);
        $nuevoEstado = $data['estado'] ?? null;

        if (!in_array($nuevoEstado, ['Aceptada', 'Rechazada', 'Pendiente'])) {
            return $this->json(['error' => 'Estado no válido. Use: Aceptada, Rechazada'], Response::HTTP_BAD_REQUEST);
        }

        // 3. Aplicar cambio
        $inscripcion->setEstadoSolicitud($nuevoEstado);

        try {
            // El Trigger TR_Check_Cupo_Update saltará aquí si intentamos aceptar y no hay cupo
            $em->flush();

            return $this->json([
                'mensaje' => "Inscripción actualizada a: $nuevoEstado",
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
