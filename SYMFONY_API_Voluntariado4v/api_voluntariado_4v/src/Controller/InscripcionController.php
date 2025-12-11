<?php

namespace App\Controller;

use App\Entity\Actividad;
use App\Entity\Inscripcion;
use App\Entity\Voluntario;
use App\Repository\ActividadRepository;
use App\Repository\InscripcionRepository;
use App\Repository\UsuarioRepository; 
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_inscripciones_')]
final class InscripcionController extends AbstractController
{
    // ========================================================================
    // 1. INSCRIBIRSE (POST) - ¡Aquí actúan los Triggers! 🛡️
    // ========================================================================
    #[Route('/actividades/{id}/inscripciones', name: 'crear', methods: ['POST'])]
    public function inscribirse(
        int $id,
        Request $request,
        ActividadRepository $actRepo,
        UsuarioRepository $userRepo, // Buscamos Voluntario por su Usuario ID (PK)
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        
        // El frontend envía el ID del usuario logueado
        $idVoluntario = $data['id_voluntario'] ?? null; 

        if (!$idVoluntario) return $this->json(['error' => 'Falta id_voluntario'], 400);

        // 1. Recuperar Entidades
        $actividad = $actRepo->find($id);
        $voluntario = $em->getRepository(Voluntario::class)->findOneBy(['usuario' => $idVoluntario]);

        if (!$actividad || !$voluntario) {
            return $this->json(['error' => 'Actividad o Voluntario no encontrados'], 404);
        }

        // 2. Preparar objeto (sin guardar aún)
        $inscripcion = new Inscripcion();
        $inscripcion->setActividad($actividad);
        $inscripcion->setVoluntario($voluntario);
        $inscripcion->setEstadoSolicitud('Pendiente');
        $inscripcion->setFechaSolicitud(new \DateTime());

        // 3. INTENTAR GUARDAR (Aquí es donde la BBDD valida las reglas)
        try {
            $em->persist($inscripcion);
            $em->flush(); // 💥 AQUÍ saltará el Trigger si hay error (Cupo, Agenda, etc.)
            
            return $this->json(['mensaje' => 'Solicitud enviada correctamente (Pendiente)'], 201);

        } catch (\Exception $e) {
            // Capturamos el mensaje de error que viene de SQL Server (RAISERROR)
            $msg = $e->getMessage();
            
            // Mapeamos errores de SQL a mensajes amigables HTTP 409
            if (str_contains($msg, 'ERROR CUPO')) {
                return $this->json(['error' => 'No puedes inscribirte: La actividad ya está completa.'], 409);
            }
            if (str_contains($msg, 'ERROR AGENDA')) {
                return $this->json(['error' => 'Conflicto de horario: Ya tienes otra actividad aceptada a esa hora.'], 409);
            }
            if (str_contains($msg, 'ERROR:')) { // Actividad no publicada o pasada
                return $this->json(['error' => 'La actividad no está disponible para inscripción.'], 409);
            }
            // Error de clave duplicada (PK compuesta)
            if (str_contains($msg, 'PRIMARY KEY') || str_contains($msg, 'Duplicate entry')) {
                return $this->json(['error' => 'Ya has enviado una solicitud para esta actividad.'], 409);
            }

            // Si es otro error desconocido
            return $this->json(['error' => 'Error interno al procesar inscripción: ' . $msg], 500);
        }
    }

    // ========================================================================
    // 2. VER ASPIRANTES (GET) - Vista para la Organización
    // URL: /api/actividades/{id}/inscripciones
    // ========================================================================
    #[Route('/actividades/{id}/inscripciones', name: 'listar_aspirantes', methods: ['GET'])]
    public function listarAspirantes(int $id, InscripcionRepository $repo): JsonResponse
    {
        // Buscamos todas las inscripciones de esa actividad
        $inscripciones = $repo->findBy(['actividad' => $id]);
        
        $resultado = [];
        foreach ($inscripciones as $ins) {
            $vol = $ins->getVoluntario();
            // Formato según YAML: InscripcionOrgView
            $resultado[] = [
                'id_voluntario'   => $vol->getUsuario()->getId(), // ID Usuario
                'nombre_completo' => $vol->getNombre() . ' ' . $vol->getApellidos(),
                'fecha_solicitud' => $ins->getFechaSolicitud()->format('Y-m-d H:i:s'),
                'estado'          => $ins->getEstadoSolicitud()
            ];
        }

        return $this->json($resultado);
    }

    // ========================================================================
    // 3. CAMBIAR ESTADO (PATCH) - Aceptar/Rechazar/Cancelar
    // URL: /api/actividades/{idActividad}/inscripciones/{idVoluntario}
    // ========================================================================
    #[Route('/actividades/{idActividad}/inscripciones/{idVoluntario}', name: 'cambiar_estado', methods: ['PATCH'])]
    public function cambiarEstado(
        int $idActividad,
        int $idVoluntario,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        // Buscamos la inscripción exacta
        $inscripcion = $em->getRepository(Inscripcion::class)->findOneBy([
            'actividad' => $idActividad,
            'voluntario' => $idVoluntario // idVoluntario es la PK de Usuario
        ]);

        if (!$inscripcion) return $this->json(['error' => 'Inscripción no encontrada'], 404);

        $data = json_decode($request->getContent(), true);
        $nuevoEstado = $data['estado'] ?? null;
        
        if (!in_array($nuevoEstado, ['Aceptada', 'Rechazada', 'Cancelada', 'Pendiente'])) {
            return $this->json(['error' => 'Estado inválido'], 400);
        }

        $inscripcion->setEstadoSolicitud($nuevoEstado);
        $inscripcion->setUpdatedAt(new \DateTime());

        try {
            $em->flush(); // 💥 AQUÍ salta el Trigger TR_Check_Cupo_Update si intentamos aceptar y ya está lleno
            return $this->json(['mensaje' => 'Estado actualizado a ' . $nuevoEstado]);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'ERROR NEGOCIO')) { // Mensaje definido en tu Trigger Update
                return $this->json(['error' => 'No se puede aceptar: El cupo se ha llenado mientras revisabas.'], 409);
            }
            return $this->json(['error' => 'Error al actualizar estado'], 500);
        }
    }

    // ========================================================================
    // 4. MIS INSCRIPCIONES (GET) - Vista para el Voluntario
    // URL: /api/voluntarios/{id}/inscripciones
    // ========================================================================
    #[Route('/voluntarios/{id}/inscripciones', name: 'mis_inscripciones', methods: ['GET'])]
    public function misInscripciones(int $id, InscripcionRepository $repo): JsonResponse
    {
        // Buscamos inscripciones donde el voluntario tenga ese ID de usuario
        // Ojo: En Doctrine la relación se llama 'voluntario', que a su vez es el objeto Voluntario cuyo ID es el usuario.
        // Doctrine es listo y si pasamos el ID entero, suele entenderlo si es la PK.
        $inscripciones = $repo->findBy(['voluntario' => $id]);

        $resultado = [];
        foreach ($inscripciones as $ins) {
            $act = $ins->getActividad();
            // Formato según YAML: InscripcionVolView
            $resultado[] = [
                'id_actividad'     => $act->getId(),
                'titulo_actividad' => $act->getTitulo(),
                'fecha_actividad'  => $act->getFechaInicio()->format('Y-m-d H:i:s'),
                'estado'           => $ins->getEstadoSolicitud()
            ];
        }

        return $this->json($resultado);
    }
}