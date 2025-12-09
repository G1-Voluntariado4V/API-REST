<?php

namespace App\Controller;

use App\Entity\Inscripcion;
use App\Entity\Voluntario;
use App\Entity\Actividad;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_inscripciones_')]
final class InscripcionController extends AbstractController
{
    // ========================================================================
    // 1. INSCRIBIRSE A UNA ACTIVIDAD (POST)
    // URL: /api/actividades/{idActividad}/inscripciones
    // Body: { "id_voluntario": 2 }
    // ========================================================================
    #[Route('/actividades/{idActividad}/inscripciones', name: 'crear', methods: ['POST'])]
    public function inscribirse(
        int $idActividad,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // 1. Validar datos
        if (!isset($data['id_voluntario'])) {
            return $this->json(['error' => 'Falta id_voluntario'], 400);
        }

        // 2. Buscar Entidades
        $actividad = $entityManager->getRepository(Actividad::class)->find($idActividad);
        // Ojo: Buscamos por 'usuario' (que es la PK de Voluntario)
        $voluntario = $entityManager->getRepository(Voluntario::class)->findOneBy(['usuario' => $data['id_voluntario']]);

        if (!$actividad || !$voluntario) {
            return $this->json(['error' => 'Actividad o Voluntario no encontrados'], 404);
        }

        // 3. Verificar si ya existe inscripción
        $existe = $entityManager->getRepository(Inscripcion::class)->findOneBy([
            'actividad' => $idActividad,
            'voluntario' => $data['id_voluntario']
        ]);

        if ($existe) {
            return $this->json(['error' => 'Ya estás inscrito en esta actividad'], 409);
        }

        // 4. Crear Inscripción
        $inscripcion = new Inscripcion();
        $inscripcion->setActividad($actividad);
        $inscripcion->setVoluntario($voluntario);
        $inscripcion->setEstadoSolicitud('Pendiente');
        $inscripcion->setFechaSolicitud(new \DateTime());

        $entityManager->persist($inscripcion);
        $entityManager->flush();

        return $this->json(['mensaje' => 'Inscripción realizada con éxito', 'estado' => 'Pendiente'], 201);
    }

    // ========================================================================
    // 2. CAMBIAR ESTADO (Aceptar/Rechazar/Cancelar) (PATCH)
    // URL: /api/actividades/{idActividad}/inscripciones/{idVoluntario}
    // Body: { "estado": "Aceptada" }
    // ========================================================================
    #[Route('/actividades/{idActividad}/inscripciones/{idVoluntario}', name: 'cambiar_estado', methods: ['PATCH', 'PUT'])]
    public function cambiarEstado(
        int $idActividad,
        int $idVoluntario,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $inscripcion = $entityManager->getRepository(Inscripcion::class)->findOneBy([
            'actividad' => $idActividad,
            'voluntario' => $idVoluntario
        ]);

        if (!$inscripcion) {
            return $this->json(['error' => 'Inscripción no encontrada'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $nuevoEstado = $data['estado'] ?? null;
        $estadosValidos = ['Pendiente', 'Aceptada', 'Rechazada', 'Cancelada', 'Finalizada'];

        if (!in_array($nuevoEstado, $estadosValidos)) {
            return $this->json(['error' => 'Estado inválido. Posibles: ' . implode(', ', $estadosValidos)], 400);
        }

        $inscripcion->setEstadoSolicitud($nuevoEstado);
        $inscripcion->setUpdatedAt(new \DateTime());

        $entityManager->flush();

        return $this->json(['mensaje' => 'Estado actualizado a ' . $nuevoEstado], 200);
    }
}
