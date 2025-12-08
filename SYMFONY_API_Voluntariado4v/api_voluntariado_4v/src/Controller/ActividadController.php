<?php

namespace App\Controller;

use App\Entity\Actividad;
use App\Repository\ActividadRepository;
use App\Repository\OrganizacionRepository;
use App\Repository\ODSRepository;
use App\Repository\TipoVoluntariadoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
final class ActividadController extends AbstractController
{
    // ========================================================================
    // 1. LISTAR ACTIVIDADES (GET)
    // ========================================================================
    #[Route('/actividades', name: 'listar_actividades', methods: ['GET'])]
    public function index(ActividadRepository $actividadRepository): JsonResponse
    {
        // Filtramos para no mostrar las borradas (Soft Delete)
        // Podríamos filtrar también por 'estadoPublicacion' => 'Publicada' si es para el home
        $actividades = $actividadRepository->findBy(['deletedAt' => null]);

        // Usamos el grupo 'actividad:read' que pusimos en la Entidad
        return $this->json($actividades, 200, [], ['groups' => 'actividad:read']);
    }

    // ========================================================================
    // 2. CREAR ACTIVIDAD (POST)
    // ========================================================================
    #[Route('/actividades', name: 'crear_actividad', methods: ['POST'])]
    public function crear(
        Request $request,
        EntityManagerInterface $entityManager,
        OrganizacionRepository $orgRepo,
        ODSRepository $odsRepo,
        TipoVoluntariadoRepository $tipoRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // A. Validaciones básicas
        if (!isset($data['id_organizacion'], $data['titulo'], $data['fecha_inicio'], $data['duracion_horas'], $data['cupo_maximo'])) {
            return $this->json(['error' => 'Faltan datos obligatorios'], 400);
        }

        // B. Buscar la Organización dueña
        $organizacion = $orgRepo->find($data['id_organizacion']);
        if (!$organizacion) {
            return $this->json(['error' => 'Organización no encontrada'], 404);
        }

        // C. Crear el objeto Actividad
        $actividad = new Actividad();
        $actividad->setOrganizacion($organizacion);
        $actividad->setTitulo($data['titulo']);
        $actividad->setDescripcion($data['descripcion'] ?? null);
        $actividad->setUbicacion($data['ubicacion'] ?? null);
        $actividad->setDuracionHoras($data['duracion_horas']);
        $actividad->setCupoMaximo($data['cupo_maximo']);
        $actividad->setEstadoPublicacion('En revision'); // Default seguro

        // D. Convertir fecha (String -> DateTime)
        try {
            $actividad->setFechaInicio(new \DateTime($data['fecha_inicio']));
        } catch (\Exception $e) {
            return $this->json(['error' => 'Formato de fecha inválido (Use YYYY-MM-DD HH:MM:SS)'], 400);
        }

        // E. Asignar ODS (Muchos a Muchos)
        // Esperamos: "ods_ids": [1, 13]
        if (!empty($data['ods_ids']) && is_array($data['ods_ids'])) {
            foreach ($data['ods_ids'] as $idOds) {
                $ods = $odsRepo->find($idOds);
                if ($ods) $actividad->addOd($ods); // Nota: revisa si tu método es addOd o addOds en la Entidad
            }
        }

        // F. Asignar Tipos de Voluntariado
        // Esperamos: "tipo_ids": [2]
        if (!empty($data['tipo_ids']) && is_array($data['tipo_ids'])) {
            foreach ($data['tipo_ids'] as $idTipo) {
                $tipo = $tipoRepo->find($idTipo);
                if ($tipo) $actividad->addTiposVoluntariado($tipo);
            }
        }

        // G. Guardar
        $entityManager->persist($actividad);
        $entityManager->flush();

        return $this->json($actividad, 201, [], ['groups' => 'actividad:read']);
    }


    // ========================================================================
    // 3. ACTUALIZAR ACTIVIDAD (PUT)
    // ========================================================================
    #[Route('/actividades/{id}', name: 'actualizar_actividad', methods: ['PUT'])]
    public function actualizar(
        int $id,
        Request $request,
        ActividadRepository $actividadRepository,
        ODSRepository $odsRepo,
        TipoVoluntariadoRepository $tipoRepo,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $actividad = $actividadRepository->find($id);
        if (!$actividad) {
            return $this->json(['error' => 'Actividad no encontrada'], 404);
        }

        $data = json_decode($request->getContent(), true);

        // Actualizamos campos simples si vienen en el JSON
        if (isset($data['titulo'])) $actividad->setTitulo($data['titulo']);
        if (isset($data['descripcion'])) $actividad->setDescripcion($data['descripcion']);
        if (isset($data['ubicacion'])) $actividad->setUbicacion($data['ubicacion']);
        if (isset($data['duracion_horas'])) $actividad->setDuracionHoras($data['duracion_horas']);
        if (isset($data['cupo_maximo'])) $actividad->setCupoMaximo($data['cupo_maximo']);

        // Actualizar Fecha
        if (isset($data['fecha_inicio'])) {
            try {
                $actividad->setFechaInicio(new \DateTime($data['fecha_inicio']));
            } catch (\Exception $e) {
                return $this->json(['error' => 'Formato de fecha inválido'], 400);
            }
        }

        // Actualizar ODS (Lógica de sincronización)
        // Si nos envían una lista nueva, borramos los viejos y ponemos los nuevos
        if (isset($data['ods_ids']) && is_array($data['ods_ids'])) {
            // 1. Limpiar actuales
            foreach ($actividad->getOds() as $odExisting) {
                $actividad->removeOd($odExisting);
            }
            // 2. Añadir nuevos
            foreach ($data['ods_ids'] as $idOds) {
                $ods = $odsRepo->find($idOds);
                if ($ods) $actividad->addOd($ods);
            }
        }

        // Actualizar Tipos de Voluntariado
        if (isset($data['tipo_ids']) && is_array($data['tipo_ids'])) {
            // 1. Limpiar actuales
            foreach ($actividad->getTiposVoluntariado() as $tipoExisting) {
                $actividad->removeTiposVoluntariado($tipoExisting);
            }
            // 2. Añadir nuevos
            foreach ($data['tipo_ids'] as $idTipo) {
                $tipo = $tipoRepo->find($idTipo);
                if ($tipo) $actividad->addTiposVoluntariado($tipo);
            }
        }

        $actividad->setUpdatedAt(new \DateTime());
        $entityManager->flush();

        return $this->json($actividad, 200, [], ['groups' => 'actividad:read']);
    }

    // ========================================================================
    // 4. ELIMINAR ACTIVIDAD (DELETE / SOFT DELETE)
    // ========================================================================
    #[Route('/actividades/{id}', name: 'eliminar_actividad', methods: ['DELETE'])]
    public function eliminar(
        int $id,
        ActividadRepository $actividadRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $actividad = $actividadRepository->find($id);
        if (!$actividad) {
            return $this->json(['error' => 'Actividad no encontrada'], 404);
        }

        // Soft Delete: Marcamos fecha y cambiamos estado
        $actividad->setDeletedAt(new \DateTimeImmutable());
        $actividad->setEstadoPublicacion('Cancelada');

        $entityManager->flush();

        return $this->json(['mensaje' => 'Actividad cancelada y eliminada correctamente'], 200);
    }
}
