<?php

namespace App\Controller;

use App\Entity\Actividad;
use App\Entity\Usuario;
use App\Entity\Inscripcion;
use App\Model\Actividad\ActividadResponseDTO;
use App\Repository\ActividadRepository;
use App\Repository\UsuarioRepository;
use App\Repository\InscripcionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Actividades', description: 'Gestión de actividades de voluntariado')]
final class ActividadController extends AbstractController
{
    // ========================================================================
    // 1. LISTAR ACTIVIDADES PÚBLICAS (GET)
    // ========================================================================
    #[Route('/actividades', name: 'listar_actividades', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Lista de actividades publicadas'
    )]
    public function index(ActividadRepository $repo, Request $request): JsonResponse
    {
        $actividades = $repo->findBy(['estadoPublicacion' => 'Publicada']);
        $baseUrl = $request->getSchemeAndHttpHost();

        $dtos = array_map(fn($a) => ActividadResponseDTO::fromEntity($a, $baseUrl), $actividades);

        return $this->json($dtos, 200);
    }

    // ========================================================================
    // 2. DETALLE ACTIVIDAD (GET)
    // ========================================================================
    #[Route('/actividades/{id}', name: 'detalle_actividad', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Detalle de una actividad'
    )]
    public function detalle(int $id, ActividadRepository $repo, Request $request): JsonResponse
    {
        $actividad = $repo->find($id);
        if (!$actividad) {
            return $this->json(['error' => 'Actividad no encontrada'], 404);
        }

        $baseUrl = $request->getSchemeAndHttpHost();
        $dto = ActividadResponseDTO::fromEntity($actividad, $baseUrl);

        return $this->json($dto, 200);
    }

    // ========================================================================
    // 3. INSCRIBIRSE A UNA ACTIVIDAD (POST)
    // ========================================================================
    #[Route('/actividades/{id}/inscripcion', name: 'inscribirse_actividad', methods: ['POST'])]
    #[OA\Parameter(name: 'X-User-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 201,
        description: 'Inscripción realizada'
    )]
    public function inscribirse(
        int $id,
        Request $request,
        ActividadRepository $actRepo,
        UsuarioRepository $userRepo,
        \App\Repository\VoluntarioRepository $volRepo,
        InscripcionRepository $inscRepo,
        EntityManagerInterface $em
    ): JsonResponse
    {
        $userId = $request->headers->get('X-User-Id');
        if (!$userId) {
            return $this->json(['error' => 'Usuario no identificado'], 401);
        }

        $usuario = $userRepo->find($userId);
        if (!$usuario) {
            return $this->json(['error' => 'Usuario no encontrado'], 404);
        }

        $rolNormalizado = strtolower(str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $usuario->getRol()->getNombre()));
        if (strpos($rolNormalizado, 'voluntar') === false) {
            return $this->json(['error' => 'Solo los voluntarios pueden inscribirse'], 403);
        }

        $actividad = $actRepo->find($id);
        if (!$actividad) {
            return $this->json(['error' => 'Actividad no encontrada'], 404);
        }

        if ($actividad->getEstadoPublicacion() !== 'Publicada') {
            return $this->json(['error' => 'Esta actividad no está disponible'], 400);
        }

        $voluntario = $volRepo->findOneBy(['usuario' => $usuario]);
        if (!$voluntario) {
            return $this->json(['error' => 'Perfil de voluntario no encontrado'], 404);
        }

        // Verificar si ya está inscrito
        $existente = $inscRepo->findOneBy([
            'voluntario' => $voluntario,
            'actividad' => $actividad
        ]);
        if ($existente) {
            return $this->json(['error' => 'Ya estás inscrito en esta actividad'], 400);
        }

        $inscripciones = 0;
        foreach ($actividad->getInscripciones() as $inscb) {
            if ($inscb->getEstadoSolicitud() === 'Aceptada' || $inscb->getEstadoSolicitud() === 'Confirmada') {
                $inscripciones++;
            }
        }

        if ($inscripciones >= $actividad->getCupoMaximo()) {
            return $this->json(['error' => 'No hay cupo disponible'], 400);
        }

        $inscripcion = new Inscripcion();
        $inscripcion->setVoluntario($voluntario);
        $inscripcion->setActividad($actividad);
        $inscripcion->setFechaSolicitud(new \DateTime());
        $inscripcion->setEstadoSolicitud('Pendiente');

        $em->persist($inscripcion);
        $em->flush();

        return $this->json(['mensaje' => 'Inscripción realizada correctamente'], 201);
    }

    // ========================================================================
    // 4. CANCELAR INSCRIPCIÓN (DELETE)
    // ========================================================================
    #[Route('/actividades/{id}/inscripcion', name: 'cancelar_inscripcion', methods: ['DELETE'])]
    #[OA\Parameter(name: 'X-User-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function cancelarInscripcion(
        int $id,
        Request $request,
        ActividadRepository $actRepo,
        UsuarioRepository $userRepo,
        InscripcionRepository $inscRepo,
        EntityManagerInterface $em
    ): JsonResponse
    {
        $userId = $request->headers->get('X-User-Id');
        if (!$userId) {
            return $this->json(['error' => 'Usuario no identificado'], 401);
        }

        $usuario = $userRepo->find($userId);
        if (!$usuario) {
            return $this->json(['error' => 'Usuario no encontrado'], 404);
        }

        $actividad = $actRepo->find($id);
        if (!$actividad) {
            return $this->json(['error' => 'Actividad no encontrada'], 404);
        }

        $voluntario = $em->getRepository(\App\Entity\Voluntario::class)->findOneBy(['usuario' => $usuario]);
        if (!$voluntario) {
            return $this->json(['error' => 'Perfil de voluntario no encontrado'], 404);
        }

        $inscripcion = $inscRepo->findOneBy([
            'voluntario' => $voluntario,
            'actividad' => $actividad
        ]);

        if (!$inscripcion) {
            return $this->json(['error' => 'No estás inscrito en esta actividad'], 400);
        }

        $em->remove($inscripcion);
        $em->flush();

        return $this->json(['mensaje' => 'Inscripción cancelada'], 200);
    }

    // ========================================================================
    // 5. GESTIONAR INSCRIPCIÓN (PATCH) - Para organizaciones/coordinadores
    // ========================================================================
    /*
    // ========================================================================
    // 5. GESTIONAR INSCRIPCIÓN (PATCH) - Para organizaciones/coordinadores
    // ========================================================================
    #[Route('/actividades/{actId}/inscripciones/{volId}', name: 'gestionar_inscripcion_legacy', methods: ['PATCH'])]
    public function gestionarInscripcion(
        int $actId,
        int $volId,
        Request $request,
        ActividadRepository $actRepo,
        InscripcionRepository $inscRepo,
        EntityManagerInterface $em
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $nuevoEstado = $data['estado'] ?? null;

        if (!in_array($nuevoEstado, ['Aceptada', 'Rechazada', 'Pendiente'])) {
            return $this->json(['error' => 'Estado no válido'], 400);
        }

        $actividad = $actRepo->find($actId);
        if (!$actividad) {
            return $this->json(['error' => 'Actividad no encontrada'], 404);
        }

        $inscripcion = $inscRepo->findOneBy([
            'actividad' => $actividad,
            'voluntario' => $volId
        ]);

        if (!$inscripcion) {
            return $this->json(['error' => 'Inscripción no encontrada'], 404);
        }

        $inscripcion->setEstadoInscripcion($nuevoEstado);
        $em->flush();

        return $this->json(['mensaje' => 'Estado de inscripción actualizado'], 200);
    }
    */

    // ========================================================================
    // 6. SUBIR IMAGEN DE ACTIVIDAD (POST)
    // ========================================================================
    #[Route('/actividades/{id}/imagen', name: 'upload_imagen_actividad', methods: ['POST'])]
    public function uploadImagen(
        int $id,
        Request $request,
        ActividadRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse
    {
        try {
            $uploadsDirectory = $this->getParameter('uploads_directory');

            $actividad = $repo->find($id);
            if (!$actividad) {
                return $this->json(['error' => 'Actividad no encontrada'], 404);
            }

            $imagen = $request->files->get('imagen');
            if (!$imagen) {
                return $this->json(['error' => 'No se proporcionó imagen'], 400);
            }

            if (!$imagen->isValid()) {
                return $this->json(['error' => 'Error en la subida del archivo: ' . $imagen->getErrorMessage()], 400);
            }

            // Validar tipo (Usando client mime type como fallback si no hay guessers)
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            $mimeType = $imagen->getClientMimeType(); // Usamos el del cliente para evitar error de guesser

            if (!in_array($mimeType, $allowedTypes)) {
                return $this->json(['error' => 'Tipo de archivo no permitido: ' . $mimeType], 400);
            }

            // Validar tamaño (5MB)
            if ($imagen->getSize() > 5 * 1024 * 1024) {
                return $this->json(['error' => 'La imagen no puede superar los 5MB'], 400);
            }

            // Eliminar imagen anterior si existe
            if ($actividad->getImgActividad()) {
                $oldPath = $uploadsDirectory . '/actividades/' . $actividad->getImgActividad();
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            // Guardar nueva imagen
            $extension = $imagen->getClientOriginalExtension(); // Usar extensión original del cliente
            if (!$extension) {
                $extension = 'jpg'; // Fallback extension
            }
            $newFilename = 'actividad_' . $id . '_' . uniqid() . '.' . $extension;

            if (!is_dir($uploadsDirectory . '/actividades')) {
                mkdir($uploadsDirectory . '/actividades', 0777, true);
            }

            $imagen->move($uploadsDirectory . '/actividades', $newFilename);

            $actividad->setImgActividad($newFilename);
            $em->flush();

            $baseUrl = $request->getSchemeAndHttpHost();
            return $this->json([
                'mensaje' => 'Imagen subida correctamente',
                'img_url' => $baseUrl . '/uploads/actividades/' . $newFilename
            ], 200);

        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'Error al subir la imagen: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    // ========================================================================
    // 7. ACTIVIDADES PRÓXIMAS (GET)
    // ========================================================================
    #[Route('/actividades/proximas', name: 'actividades_proximas', methods: ['GET'], priority: 10)]
    public function proximas(ActividadRepository $repo, Request $request): JsonResponse
    {
        $actividades = $repo->createQueryBuilder('a')
            ->where('a.estadoPublicacion = :estado')
            ->andWhere('a.fechaInicio >= :now')
            ->setParameter('estado', 'Publicada')
            ->setParameter('now', new \DateTime())
            ->orderBy('a.fechaInicio', 'ASC')
            ->setMaxResults(6)
            ->getQuery()
            ->getResult();

        $baseUrl = $request->getSchemeAndHttpHost();
        $dtos = array_map(fn($a) => ActividadResponseDTO::fromEntity($a, $baseUrl), $actividades);

        return $this->json($dtos, 200);
    }

    // ========================================================================
    // 8. BUSCAR ACTIVIDADES (GET)
    // ========================================================================
    #[Route('/actividades/buscar', name: 'buscar_actividades', methods: ['GET'], priority: 10)]
    public function buscar(Request $request, ActividadRepository $repo): JsonResponse
    {
        $query = $request->query->get('q', '');
        $tipo = $request->query->get('tipo');
        $ods = $request->query->get('ods');

        $qb = $repo->createQueryBuilder('a')
            ->where('a.estadoPublicacion = :estado')
            ->setParameter('estado', 'Publicada');

        if ($query) {
            $qb->andWhere('a.titulo LIKE :query OR a.descripcion LIKE :query')
               ->setParameter('query', '%' . $query . '%');
        }

        if ($tipo) {
            $qb->join('a.tiposVoluntariado', 't')
               ->andWhere('t.id = :tipo')
               ->setParameter('tipo', $tipo);
        }

        if ($ods) {
            $qb->join('a.ods', 'o')
               ->andWhere('o.id = :ods')
               ->setParameter('ods', $ods);
        }

        $actividades = $qb->orderBy('a.fechaInicio', 'ASC')
                         ->getQuery()
                         ->getResult();

        $baseUrl = $request->getSchemeAndHttpHost();
        $dtos = array_map(fn($a) => ActividadResponseDTO::fromEntity($a, $baseUrl), $actividades);

        return $this->json($dtos, 200);
    }
}
