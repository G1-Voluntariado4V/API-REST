<?php

namespace App\Controller;

use App\Entity\Usuario;
use App\Entity\Voluntario;
use App\Entity\VoluntarioIdioma;
use App\Entity\Actividad;
use App\Entity\Inscripcion;
use App\Repository\RolRepository;
use App\Repository\CursoRepository;
use App\Repository\IdiomaRepository;
use App\Repository\UsuarioRepository;
use App\Repository\TipoVoluntariadoRepository;
use App\Repository\ActividadRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('/api', name: 'api_')]
#[OA\Tag(name: 'Voluntarios', description: 'Gestión de perfiles de voluntarios, inscripciones y estadísticas')]
final class VoluntarioController extends AbstractController
{
    // ========================================================================
    // 1. LISTADO OPTIMIZADO (Vista SQL)
    // ========================================================================
    #[Route('/voluntarios', name: 'listar_voluntarios', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Listado completo de voluntarios activos (Vista SQL)',
        content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'object'))
    )]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        $conn = $em->getConnection();
        $sql = 'SELECT * FROM VW_Voluntarios_Activos';
        
        try {
            $voluntarios = $conn->executeQuery($sql)->fetchAllAssociative();
            return $this->json($voluntarios, Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al obtener voluntarios: ' . $e->getMessage()], 
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    // ========================================================================
    // 2. RECOMENDACIONES (Stored Procedure)
    // ========================================================================
    #[Route('/voluntarios/{id}/recomendaciones', name: 'recomendaciones_voluntario', methods: ['GET'])]
    #[OA\Parameter(name: 'id', description: 'ID del Usuario/Voluntario', in: 'path', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Actividades recomendadas según preferencias ODS',
        content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'object'))
    )]
    public function recomendaciones(int $id, EntityManagerInterface $em): JsonResponse
    {
        $conn = $em->getConnection();
        $sql = 'EXEC SP_Get_Recomendaciones_Voluntario @id_voluntario = :id';
        
        try {
            $stmt = $conn->executeQuery($sql, ['id' => $id]);
            $actividades = $stmt->fetchAllAssociative();
            return $this->json($actividades, Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al calcular recomendaciones'], 
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    // ========================================================================
    // 3. REGISTRAR VOLUNTARIO (Transaccional) -> ¡ESTE ES EL QUE TE FALTABA!
    // ========================================================================
    #[Route('/voluntarios', name: 'registro_voluntario', methods: ['POST'])]
    #[OA\RequestBody(
        description: 'Datos completos de registro (Usuario + Perfil + Idiomas + Preferencias)',
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'google_id', type: 'string', example: '12345'),
                new OA\Property(property: 'correo', type: 'string', example: 'vol@test.com'),
                new OA\Property(property: 'nombre', type: 'string', example: 'Juan'),
                new OA\Property(property: 'apellidos', type: 'string', example: 'Pérez'),
                new OA\Property(property: 'dni', type: 'string', example: '12345678A'),
                new OA\Property(property: 'fecha_nac', type: 'string', format: 'date', example: '2000-01-01'),
                new OA\Property(property: 'id_curso_actual', type: 'integer', example: 1),
                new OA\Property(property: 'preferencias_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 3]),
                new OA\Property(property: 'idiomas', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id_idioma', type: 'integer'),
                        new OA\Property(property: 'nivel', type: 'string', example: 'B2')
                    ]
                ))
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Voluntario registrado correctamente')]
    #[OA\Response(response: 409, description: 'Usuario duplicado')]
    public function registrar(
        Request $request,
        EntityManagerInterface $entityManager,
        RolRepository $rolRepository,
        CursoRepository $cursoRepository,
        IdiomaRepository $idiomaRepository,
        TipoVoluntariadoRepository $tipoRepo
    ): JsonResponse {

        $data = json_decode($request->getContent(), true);

        if (!isset($data['google_id'], $data['correo'], $data['nombre'], $data['apellidos'])) {
            return $this->json(['error' => 'Faltan datos obligatorios'], Response::HTTP_BAD_REQUEST);
        }

        $entityManager->beginTransaction();

        try {
            // A. USUARIO BASE
            $usuario = new Usuario();
            $usuario->setCorreo($data['correo']);
            $usuario->setGoogleId($data['google_id']);
            $usuario->setEstadoCuenta('Activa'); 
            
            $rolVoluntario = $rolRepository->findOneBy(['nombreRol' => 'Voluntario']); 
            if (!$rolVoluntario) throw new \Exception("Error interno: Rol 'Voluntario' no configurado en BBDD");
            $usuario->setRol($rolVoluntario);

            $entityManager->persist($usuario);
            $entityManager->flush(); 

            // B. PERFIL VOLUNTARIO
            $voluntario = new Voluntario();
            $voluntario->setUsuario($usuario); 
            $voluntario->setNombre($data['nombre']);
            $voluntario->setApellidos($data['apellidos']);
            $voluntario->setTelefono($data['telefono'] ?? null);
            $voluntario->setDni($data['dni'] ?? null);
            $voluntario->setCarnetConducir($data['carnet_conducir'] ?? false);
            $voluntario->setImgPerfil($data['img_perfil'] ?? null);

            if (!empty($data['fecha_nac'])) {
                try { $voluntario->setFechaNac(new \DateTime($data['fecha_nac'])); } catch (\Exception $e) {}
            }

            if (!empty($data['id_curso_actual'])) {
                $curso = $cursoRepository->find($data['id_curso_actual']);
                if ($curso) $voluntario->setCursoActual($curso);
            }

            // C. IDIOMAS
            if (!empty($data['idiomas']) && is_array($data['idiomas'])) {
                foreach ($data['idiomas'] as $idiomaData) {
                    $idiomaEntity = $idiomaRepository->find($idiomaData['id_idioma']);
                    if ($idiomaEntity) {
                        $vi = new VoluntarioIdioma();
                        $vi->setVoluntario($voluntario);
                        $vi->setIdioma($idiomaEntity);
                        $vi->setNivel($idiomaData['nivel'] ?? 'Básico');
                        $entityManager->persist($vi);
                    }
                }
            }

            // D. PREFERENCIAS
            if (!empty($data['preferencias_ids']) && is_array($data['preferencias_ids'])) {
                foreach ($data['preferencias_ids'] as $idTipo) {
                    $tipo = $tipoRepo->find($idTipo);
                    if ($tipo) $voluntario->addPreferencia($tipo);
                }
            }

            $entityManager->persist($voluntario);
            $entityManager->flush();
            $entityManager->commit();

            return $this->json($voluntario, Response::HTTP_CREATED, [], ['groups' => 'usuario:read']);

        } catch (UniqueConstraintViolationException $e) {
            $entityManager->rollback();
            return $this->json(['error' => 'El correo o DNI ya existen'], Response::HTTP_CONFLICT);
        } catch (\Exception $e) {
            $entityManager->rollback();
            return $this->json(['error' => 'Error al registrar: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ========================================================================
    // 4. GET ONE
    // ========================================================================
    #[Route('/voluntarios/{id}', name: 'get_voluntario', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Detalle del voluntario')]
    public function getOne(int $id, UsuarioRepository $userRepo): JsonResponse 
    {
        $usuario = $userRepo->find($id);
        if (!$usuario || $usuario->getDeletedAt()) {
            return $this->json(['error' => 'Voluntario no encontrado'], Response::HTTP_NOT_FOUND);
        }
        return $this->json($usuario, Response::HTTP_OK, [], ['groups' => 'usuario:read']);
    }

    // ========================================================================
    // 5. ACTUALIZAR (PUT)
    // ========================================================================
    #[Route('/voluntarios/{id}', name: 'actualizar_voluntario', methods: ['PUT'])]
    #[OA\Response(response: 200, description: 'Perfil actualizado')]
    public function actualizar(
        int $id,
        Request $request,
        UsuarioRepository $usuarioRepo,
        TipoVoluntariadoRepository $tipoRepo, 
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $usuario = $usuarioRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        
        $voluntario = $entityManager->getRepository(Voluntario::class)->findOneBy(['usuario' => $usuario]);
        if (!$voluntario) return $this->json(['error' => 'Perfil no encontrado'], Response::HTTP_NOT_FOUND);

        $data = json_decode($request->getContent(), true);

        if (isset($data['nombre'])) $voluntario->setNombre($data['nombre']);
        if (isset($data['apellidos'])) $voluntario->setApellidos($data['apellidos']);
        if (isset($data['telefono'])) $voluntario->setTelefono($data['telefono']);
        
        if (isset($data['preferencias_ids']) && is_array($data['preferencias_ids'])) {
            foreach ($voluntario->getPreferencias() as $pref) {
                $voluntario->removePreferencia($pref);
            }
            foreach ($data['preferencias_ids'] as $idTipo) {
                $tipo = $tipoRepo->find($idTipo);
                if ($tipo) $voluntario->addPreferencia($tipo);
            }
        }

        $entityManager->flush();
        return $this->json($voluntario, Response::HTTP_OK, [], ['groups' => 'usuario:read']);
    }

    // ========================================================================
    // 6. ELIMINAR (DELETE) - Usando SP para consistencia
    // ========================================================================
    #[Route('/voluntarios/{id}', name: 'borrar_voluntario', methods: ['DELETE'])]
    #[OA\Response(response: 200, description: 'Voluntario eliminado (Soft Delete)')]
    public function eliminar(int $id, UsuarioRepository $usuarioRepo, EntityManagerInterface $em): JsonResponse 
    {
        $usuario = $usuarioRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);

        $sql = 'EXEC SP_SoftDelete_Usuario @id_usuario = :id';
        
        try {
            $em->getConnection()->executeStatement($sql, ['id' => $id]);
            return $this->json(['mensaje' => 'Usuario eliminado correctamente (Soft Delete)'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al eliminar'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    // ========================================================================
    // 7. RESTAURAR
    // ========================================================================
    #[Route('/voluntarios/{id}/restaurar', name: 'restaurar_voluntario', methods: ['POST'])]
    #[OA\Response(response: 200, description: 'Usuario restaurado')]
    public function restaurar(int $id, EntityManagerInterface $em): JsonResponse
    {
        $sql = 'EXEC SP_Restore_Usuario @id_usuario = :id';
        try {
            $em->getConnection()->executeStatement($sql, ['id' => $id]);
            return $this->json(['mensaje' => 'Usuario restaurado'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al restaurar'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ========================================================================
    // 8. INSCRIBIRSE A UNA ACTIVIDAD (POST)
    // ========================================================================
    #[Route('/voluntarios/{id}/actividades/{idActividad}', name: 'inscribirse_actividad', methods: ['POST'])]
    #[OA\Response(response: 201, description: 'Inscripción realizada (Pendiente)')]
    #[OA\Response(response: 409, description: 'Error de negocio (Cupo, Fechas, Ya inscrito)')]
    public function inscribirse(
        int $id, 
        int $idActividad, 
        UsuarioRepository $userRepo,
        ActividadRepository $actRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        // 1. Validar Voluntario
        $usuario = $userRepo->find($id);
        if (!$usuario || !$usuario->getVoluntario()) { 
             $voluntario = $em->getRepository(Voluntario::class)->findOneBy(['usuario' => $usuario]);
        } else {
             $voluntario = $usuario->getVoluntario();
        }

        if (!$voluntario) return $this->json(['error' => 'Voluntario no encontrado'], Response::HTTP_NOT_FOUND);

        // 2. Validar Actividad
        $actividad = $actRepo->find($idActividad);
        if (!$actividad) return $this->json(['error' => 'Actividad no encontrada'], Response::HTTP_NOT_FOUND);

        // 3. Crear Inscripción
        $inscripcion = new Inscripcion();
        $inscripcion->setVoluntario($voluntario);
        $inscripcion->setActividad($actividad);
        $inscripcion->setEstadoSolicitud('Pendiente');
        $inscripcion->setFechaSolicitud(new \DateTime()); 

        try {
            $em->persist($inscripcion);
            $em->flush();
            return $this->json(['mensaje' => 'Inscripción solicitada correctamente'], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'No se pudo realizar la inscripción. Verifique cupos, fechas o si ya está inscrito.',
                'detalle' => $e->getMessage() 
            ], Response::HTTP_CONFLICT);
        }
    }

    // ========================================================================
    // 9. DESAPUNTARSE (DELETE)
    // ========================================================================
    #[Route('/voluntarios/{id}/actividades/{idActividad}', name: 'desapuntarse_actividad', methods: ['DELETE'])]
    #[OA\Response(response: 200, description: 'Inscripción cancelada')]
    public function desapuntarse(
        int $id, 
        int $idActividad, 
        EntityManagerInterface $em
    ): JsonResponse {
        $inscripcion = $em->getRepository(Inscripcion::class)->findOneBy([
            'voluntario' => $id, 
            'actividad' => $idActividad
        ]);

        if (!$inscripcion) {
            return $this->json(['error' => 'No estás inscrito en esta actividad'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($inscripcion);
        $em->flush();

        return $this->json(['mensaje' => 'Te has desapuntado correctamente'], Response::HTTP_OK);
    }

    // ========================================================================
    // 10. ESTADÍSTICAS E HISTORIAL (GET)
    // ========================================================================
    #[Route('/voluntarios/{id}/historial', name: 'historial_voluntario', methods: ['GET'])]
    #[OA\Response(
        response: 200, 
        description: 'Estadísticas y lista de actividades',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'resumen', type: 'object'),
                new OA\Property(property: 'actividades', type: 'array', items: new OA\Items(type: 'object'))
            ]
        )
    )]
    public function historial(int $id, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        $usuario = $userRepo->find($id);
        $voluntario = $em->getRepository(Voluntario::class)->findOneBy(['usuario' => $usuario]);

        if (!$voluntario) return $this->json(['error' => 'Voluntario no encontrado'], Response::HTTP_NOT_FOUND);

        $inscripciones = $em->getRepository(Inscripcion::class)->findBy(['voluntario' => $voluntario]);

        $historial = [];
        $horasTotales = 0;
        $participacionesConfirmadas = 0;

        foreach ($inscripciones as $insc) {
            $act = $insc->getActividad();
            
            if (in_array($insc->getEstadoSolicitud(), ['Aceptada', 'Finalizada'])) {
                $participacionesConfirmadas++;
                $horasTotales += $act->getDuracionHoras();
            }

            $historial[] = [
                'id_actividad' => $act->getId(),
                'titulo' => $act->getTitulo(),
                'fecha_inicio' => $act->getFechaInicio()->format('Y-m-d H:i:s'),
                'estado_solicitud' => $insc->getEstadoSolicitud(), 
                'horas' => $act->getDuracionHoras()
            ];
        }

        return $this->json([
            'resumen' => [
                'total_participaciones' => $participacionesConfirmadas,
                'horas_acumuladas' => $horasTotales,
                'nivel_experiencia' => $horasTotales > 50 ? 'Experto' : ($horasTotales > 20 ? 'Intermedio' : 'Principiante')
            ],
            'actividades' => $historial
        ], Response::HTTP_OK);
    }
}