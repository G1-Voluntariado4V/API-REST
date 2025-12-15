<?php

namespace App\Controller;

use App\Entity\Usuario;
use App\Entity\Voluntario;
use App\Entity\VoluntarioIdioma;
use App\Entity\Inscripcion;
use App\Entity\Actividad;
use App\Entity\Curso;
use App\Entity\TipoVoluntariado;
use App\Entity\Idioma;
// DTOs
use App\Model\VoluntarioCreateDTO;
use App\Model\VoluntarioResponseDTO;
// Repositorios
use App\Repository\RolRepository;
use App\Repository\UsuarioRepository;
use App\Repository\ActividadRepository;
// Doctrine
use Doctrine\ORM\EntityManagerInterface;
// Symfony
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
// Swagger / OpenApi
use OpenApi\Attributes as OA;

use Nelmio\ApiDocBundle\Attribute\Model;

#[Route('', name: 'api_')]
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
        // Mantenemos tu lógica de Vista SQL porque es eficiente para listados
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
    // 2. REGISTRAR VOLUNTARIO (Transaccional + DTO)
    // ========================================================================
    #[Route('/voluntarios', name: 'registro_voluntario', methods: ['POST'])]
    #[OA\RequestBody(
        description: 'Datos completos de registro (Validado por DTO)',
        required: true,
        content: new OA\JsonContent(
            // Aquí usamos la clase Model correctamente importada
            ref: new Model(type: VoluntarioCreateDTO::class)
        )
    )]
    #[OA\Response(
        response: 201, 
        description: 'Voluntario registrado correctamente',
        content: new OA\JsonContent(
            // Aquí usamos la clase Model correctamente importada
            ref: new Model(type: VoluntarioResponseDTO::class)
        )
    )]
    #[OA\Response(response: 409, description: 'Usuario duplicado')]
    #[OA\Response(response: 422, description: 'Error de validación de datos')]
    public function registrar(
        #[MapRequestPayload] VoluntarioCreateDTO $dto, // ¡Magia de Symfony!
        EntityManagerInterface $em,
        RolRepository $rolRepository
    ): JsonResponse {

        $em->beginTransaction();
        try {
            // A. USUARIO BASE
            $usuario = new Usuario();
            $usuario->setCorreo($dto->correo);
            $usuario->setGoogleId($dto->google_id);
            $usuario->setEstadoCuenta('Activa');

            // Busca por 'nombre' o 'nombre_rol' según tu entidad Rol
            $rolVoluntario = $rolRepository->findOneBy(['nombre' => 'Voluntario']);
            if (!$rolVoluntario) throw new \Exception("Rol 'Voluntario' no encontrado");
            $usuario->setRol($rolVoluntario);

            $em->persist($usuario);

            // 🔥 FLUSH CRÍTICO: Generar ID de Usuario antes de usarlo en Voluntario
            $em->flush();

            // B. PERFIL VOLUNTARIO
            $voluntario = new Voluntario();
            $voluntario->setUsuario($usuario);
            $voluntario->setNombre($dto->nombre);
            $voluntario->setApellidos($dto->apellidos);
            $voluntario->setDni($dto->dni);
            $voluntario->setTelefono($dto->telefono);
            $voluntario->setCarnetConducir($dto->carnet_conducir);

            // Manejo de fecha (El DTO garantiza que el string es válido)
            try {
                $voluntario->setFechaNac(new \DateTime($dto->fecha_nac));
            } catch (\Exception $e) { /* Ignorar, DTO ya validó formato */
            }

            // Asignar Curso
            $curso = $em->getRepository(Curso::class)->find($dto->id_curso_actual);
            if (!$curso) throw new \Exception("Curso no encontrado (ID: {$dto->id_curso_actual})");
            $voluntario->setCursoActual($curso);

            // C. PREFERENCIAS (Tipos de Voluntariado)
            $tipoRepo = $em->getRepository(TipoVoluntariado::class);
            foreach ($dto->preferencias_ids as $tipoId) {
                $tipo = $tipoRepo->find($tipoId);
                if ($tipo) {
                    $voluntario->addPreferencia($tipo);
                }
            }

            // D. IDIOMAS
            $idiomaRepo = $em->getRepository(Idioma::class);
            foreach ($dto->idiomas as $idiomaData) {
                // $idiomaData es ['id_idioma' => 1, 'nivel' => 'B2']
                $entidadIdioma = $idiomaRepo->find($idiomaData['id_idioma']);
                if ($entidadIdioma) {
                    $vi = new VoluntarioIdioma();
                    $vi->setVoluntario($voluntario);
                    $vi->setIdioma($entidadIdioma);
                    $vi->setNivel($idiomaData['nivel']);
                    $em->persist($vi);
                }
            }

            $em->persist($voluntario);
            $em->flush();
            $em->commit();

            // Refrescar para asegurar que las relaciones se cargan bien
            $em->refresh($voluntario);

            // Respuesta limpia con DTO
            return $this->json(
                VoluntarioResponseDTO::fromEntity($voluntario),
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            $em->rollback();
            // Detectar duplicados SQL (Código 23000 suele ser integridad)
            if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), '23000')) {
                return $this->json(['error' => 'El correo o DNI ya existen'], Response::HTTP_CONFLICT);
            }
            return $this->json(['error' => 'Error al registrar: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ========================================================================
    // 3. GET ONE (Detalle) - ¡AHORA DEVUELVE DTO!
    // ========================================================================
    #[Route('/voluntarios/{id}', name: 'get_voluntario', methods: ['GET'])]
    #[OA\Response(
        response: 200, 
        description: 'Detalle del voluntario (DTO)',
        content: new OA\JsonContent(
            // Aquí usamos la clase Model correctamente importada
            ref: new Model(type: VoluntarioResponseDTO::class)
        )
    )]
    public function getOne(int $id, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        $usuario = $userRepo->find($id);

        // Validaciones básicas
        if (!$usuario || $usuario->getDeletedAt()) {
            return $this->json(['error' => 'Voluntario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        // Obtener el perfil de voluntario asociado
        $voluntario = $em->getRepository(Voluntario::class)->findOneBy(['usuario' => $usuario]);

        if (!$voluntario) {
            return $this->json(['error' => 'Perfil de voluntario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        // Devolver DTO limpio
        return $this->json(VoluntarioResponseDTO::fromEntity($voluntario), Response::HTTP_OK);
    }

    // ========================================================================
    // 4. RECOMENDACIONES (Stored Procedure)
    // ========================================================================
    #[Route('/voluntarios/{id}/recomendaciones', name: 'recomendaciones_voluntario', methods: ['GET'])]
    public function recomendaciones(int $id, EntityManagerInterface $em): JsonResponse
    {
        $conn = $em->getConnection();
        $sql = 'EXEC SP_Get_Recomendaciones_Voluntario @id_voluntario = :id';

        try {
            $stmt = $conn->executeQuery($sql, ['id' => $id]);
            $actividades = $stmt->fetchAllAssociative();
            return $this->json($actividades, Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al calcular recomendaciones'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ========================================================================
    // 5. ACTUALIZAR (PUT) - Pendiente de DTO si quieres (ahora está manual)
    // ========================================================================
    #[Route('/voluntarios/{id}', name: 'actualizar_voluntario', methods: ['PUT'])]
    #[OA\Response(
        response: 200, 
        description: 'Voluntario actualizado',
        content: new OA\JsonContent(
            // Documentamos que devolvemos el DTO actualizado
            ref: new Model(type: VoluntarioResponseDTO::class)
        )
    )]
    public function actualizar(
        int $id,
        Request $request,
        UsuarioRepository $usuarioRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $usuario = $usuarioRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);

        $voluntario = $em->getRepository(Voluntario::class)->findOneBy(['usuario' => $usuario]);
        if (!$voluntario) return $this->json(['error' => 'Perfil no encontrado'], Response::HTTP_NOT_FOUND);

        // TODO: En el futuro, crear VoluntarioUpdateDTO y usar MapRequestPayload aquí también
        $data = json_decode($request->getContent(), true);

        if (isset($data['nombre'])) $voluntario->setNombre($data['nombre']);
        if (isset($data['apellidos'])) $voluntario->setApellidos($data['apellidos']);
        if (isset($data['telefono'])) $voluntario->setTelefono($data['telefono']);

        // Actualizar preferencias si vienen
        if (isset($data['preferencias_ids']) && is_array($data['preferencias_ids'])) {
            // Limpiar anteriores
            foreach ($voluntario->getPreferencias() as $pref) {
                $voluntario->removePreferencia($pref);
            }
            // Añadir nuevas
            $tipoRepo = $em->getRepository(TipoVoluntariado::class);
            foreach ($data['preferencias_ids'] as $idTipo) {
                $tipo = $tipoRepo->find($idTipo);
                if ($tipo) $voluntario->addPreferencia($tipo);
            }
        }

        $em->flush();
        // Devolvemos el DTO actualizado
        return $this->json(VoluntarioResponseDTO::fromEntity($voluntario), Response::HTTP_OK);
    }

    // ========================================================================
    // 6. ELIMINAR (DELETE) - Usando SP
    // ========================================================================
    #[Route('/voluntarios/{id}', name: 'borrar_voluntario', methods: ['DELETE'])]
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
    // 8. INSCRIBIRSE (POST)
    // ========================================================================
    #[Route('/voluntarios/{id}/actividades/{idActividad}', name: 'inscribirse_actividad', methods: ['POST'])]
    public function inscribirse(
        int $id,
        int $idActividad,
        UsuarioRepository $userRepo,
        ActividadRepository $actRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $usuario = $userRepo->find($id);
        // Obtener Voluntario de forma segura
        $voluntario = ($usuario) ? $em->getRepository(Voluntario::class)->findOneBy(['usuario' => $usuario]) : null;

        if (!$voluntario) return $this->json(['error' => 'Voluntario no encontrado'], Response::HTTP_NOT_FOUND);

        $actividad = $actRepo->find($idActividad);
        if (!$actividad) return $this->json(['error' => 'Actividad no encontrada'], Response::HTTP_NOT_FOUND);

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
                'error' => 'No se pudo realizar la inscripción. Verifique cupos o si ya está inscrito.',
                'detalle' => $e->getMessage()
            ], Response::HTTP_CONFLICT);
        }
    }

    // ========================================================================
    // 9. DESAPUNTARSE (DELETE)
    // ========================================================================
    #[Route('/voluntarios/{id}/actividades/{idActividad}', name: 'desapuntarse_actividad', methods: ['DELETE'])]
    public function desapuntarse(
        int $id,
        int $idActividad,
        EntityManagerInterface $em
    ): JsonResponse {
        // Buscamos inscripción por IDs directos (asumiendo que $id es id_usuario/voluntario)
        $inscripcion = $em->getRepository(Inscripcion::class)->findOneBy([
            'voluntario' => $id, // Doctrine es listo y mapea el ID al objeto si es PK compartida
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
    // 10. HISTORIAL (GET)
    // ========================================================================
    #[Route('/voluntarios/{id}/historial', name: 'historial_voluntario', methods: ['GET'])]
    public function historial(int $id, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        $usuario = $userRepo->find($id);
        $voluntario = ($usuario) ? $em->getRepository(Voluntario::class)->findOneBy(['usuario' => $usuario]) : null;

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