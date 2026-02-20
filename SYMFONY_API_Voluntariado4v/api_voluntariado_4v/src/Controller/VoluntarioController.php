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
use App\Model\Voluntario\VoluntarioCreateDTO;
use App\Model\Voluntario\VoluntarioResponseDTO;
use App\Model\Voluntario\VoluntarioUpdateDTO;
use App\Model\Inscripcion\InscripcionResponseDTO;
use App\Repository\RolRepository;
use App\Repository\UsuarioRepository;
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
#[OA\Tag(name: 'Voluntarios', description: 'Gestión de perfiles, inscripciones y estadísticas')]
final class VoluntarioController extends AbstractController
{
    private function checkOwner(Request $request, int $resourceId): bool
    {
        if ($request->headers->has('X-Admin-Id')) {
            return true;
        }

        $headerId = $request->headers->get('X-User-Id');
        return $headerId && (int)$headerId === $resourceId;
    }

    #[Route('/voluntarios/{id}/estadisticas', name: 'voluntario_estadisticas', methods: ['GET'])]
    public function estadisticas(int $id, Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->checkOwner($request, $id)) return $this->json(['error' => 'Acceso denegado'], 403);

        $usuario = $userRepo->find($id);
        $voluntario = ($usuario) ? $em->getRepository(Voluntario::class)->findOneBy(['usuario' => $usuario]) : null;
        if (!$voluntario) return $this->json(['error' => 'Voluntario no encontrado'], 404);

        $inscripciones = $em->getRepository(Inscripcion::class)->findBy(['voluntario' => $voluntario]);

        $horasTotales = 0;
        $activas = 0;
        $total = 0;
        $completadas = 0;
        $pendientes = 0;

        foreach ($inscripciones as $insc) {
            $total++;
            $estado = $insc->getEstadoSolicitud();
            if ($estado === 'Aceptada' || $estado === 'Confirmada') {
                $activas++;
            } elseif ($estado === 'Finalizada') {
                $completadas++;
                $act = $insc->getActividad();
                if ($act) {
                    $horasTotales += $act->getDuracionHoras() ?? 0;
                }
            } elseif ($estado === 'Pendiente') {
                $pendientes++;
            }
        }

        return $this->json([
            'inscripciones_activas' => $activas,
            'horas_totales' => $horasTotales,
            'total_inscripciones' => $total,
            'actividades_completadas' => $completadas,
            'inscripciones_pendientes' => $pendientes
        ], 200);
    }

    #[Route('/voluntarios/{id}/inscripciones', name: 'voluntario_inscripciones', methods: ['GET'])]
    public function inscripciones(int $id, Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->checkOwner($request, $id)) return $this->json(['error' => 'Acceso denegado'], 403);

        $usuario = $userRepo->find($id);
        $voluntario = ($usuario) ? $em->getRepository(Voluntario::class)->findOneBy(['usuario' => $usuario]) : null;
        if (!$voluntario) return $this->json(['error' => 'Voluntario no encontrado'], 404);

        try {
            $inscripciones = $em->getRepository(Inscripcion::class)->findBy(['voluntario' => $voluntario], ['fechaSolicitud' => 'DESC']);

            $baseUrl = $request->getSchemeAndHttpHost();
            $actividades = [];
            foreach ($inscripciones as $ins) {
                $act = $ins->getActividad();
                if (!$act) continue;

                $ods = [];
                foreach ($act->getOds() as $od) {
                    $ods[] = [
                        'id' => $od->getId(),
                        'nombre' => $od->getNombre(),
                        'img_url' => $od->getImgOds() ? $baseUrl . '/uploads/ods/' . $od->getImgOds() : null
                    ];
                }

                $tipos = [];
                foreach ($act->getTiposVoluntariado() as $t) {
                    $tipos[] = ['id' => $t->getId(), 'nombre' => $t->getNombreTipo()];
                }

                $confirmados = 0;
                foreach ($act->getInscripciones() as $other) {
                    if (in_array($other->getEstadoSolicitud(), ['Aceptada', 'Confirmada'])) {
                        $confirmados++;
                    }
                }

                $actividades[] = [
                    'id' => ($ins->getVoluntario()->getUsuario()->getId()) . '-' . $act->getId(),
                    'estado' => $ins->getEstadoSolicitud(),
                    'actividad' => [
                        'id' => $act->getId(),
                        'titulo' => $act->getTitulo(),
                        'descripcion' => $act->getDescripcion(),
                        'fecha_inicio' => $act->getFechaInicio() ? $act->getFechaInicio()->format('Y-m-d H:i:s') : null,
                        'duracion_horas' => $act->getDuracionHoras(),
                        'cupo_maximo' => $act->getCupoMaximo(),
                        'inscritos_confirmados' => $confirmados,
                        'ubicacion' => $act->getUbicacion() ?? '',
                        'modalidad' => 'Presencial',
                        'img_url' => $act->getImgActividad() ? $baseUrl . '/uploads/actividades/' . $act->getImgActividad() : null,
                        'id_organizacion' => $act->getOrganizacion()->getId(),
                        'nombre_organizacion' => $act->getOrganizacion()->getNombre(),
                        'img_organizacion' => ($act->getOrganizacion()->getUsuario() && $act->getOrganizacion()->getUsuario()->getImgPerfil())
                                ? $baseUrl . '/uploads/usuarios/' . $act->getOrganizacion()->getUsuario()->getImgPerfil()
                                : null,
                        'ods' => $ods,
                        'tipos' => $tipos,
                    ]
                ];
            }

            return $this->json($actividades, 200);
        } catch (\Exception $e) {
            file_put_contents(__DIR__ . '/../../var/debug_500_insc.log', date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
            return $this->json(['error' => 'Internal Server Error', 'detalle' => $e->getMessage()], 500);
        }
    }

    // ========================================================================
    // 1. LISTADO (GET)
    // ========================================================================
    #[Route('/voluntarios', name: 'listar_voluntarios', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Listado completo de voluntarios activos',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'id_usuario', type: 'integer', example: 10),
                    new OA\Property(property: 'nombre', type: 'string', example: 'Ana'),
                    new OA\Property(property: 'apellidos', type: 'string', example: 'García'),
                    new OA\Property(property: 'correo_usuario', type: 'string', example: 'ana@test.com'),
                    new OA\Property(property: 'curso_actual', type: 'string', example: '2º DAW'),
                    new OA\Property(property: 'telefono', type: 'string', example: '+34 600 11 22 33'),
                    new OA\Property(property: 'estado_cuenta', type: 'string', example: 'Activa')
                ],
                type: 'object'
            )
        )
    )]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        $conn = $em->getConnection();
        $sql = "
            SELECT
                u.id_usuario, u.correo as correo_usuario, u.estado_cuenta, u.fecha_registro,
                v.nombre, v.apellidos, v.dni, v.telefono,
                CONVERT(VARCHAR, v.fecha_nac, 23) as fecha_nac,
                v.carnet_conducir,
                u.img_perfil as foto_perfil, c.nombre_curso as curso
            FROM USUARIO u
            INNER JOIN VOLUNTARIO v ON u.id_usuario = v.id_usuario
            LEFT JOIN CURSO c ON v.id_curso_actual = c.id_curso
            WHERE u.deleted_at IS NULL
        ";
        try {
            $voluntarios = $conn->executeQuery($sql)->fetchAllAssociative();
            return $this->json($voluntarios, Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al obtener datos'], 500);
        }
    }

    // ========================================================================
    // 2. REGISTRAR VOLUNTARIO (POST)
    // ========================================================================
    #[Route('/voluntarios', name: 'registro_voluntario', methods: ['POST'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: VoluntarioCreateDTO::class)))]
    #[OA\Response(response: 201, description: 'Voluntario registrado', content: new OA\JsonContent(ref: new Model(type: VoluntarioResponseDTO::class)))]
    public function registrar(
        #[MapRequestPayload] VoluntarioCreateDTO $dto,
        EntityManagerInterface $em,
        RolRepository $rolRepository,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%uploads_directory%')] string $uploadsDirectory
    ): JsonResponse {

        // 1. COMPROBACIONES MANUALES PREVIAS (Para evitar usuarios huérfanos)
        $usuarioExistente = $em->getRepository(Usuario::class)->findOneBy(['correo' => $dto->correo]);
        if ($usuarioExistente) {
            return $this->json(['error' => 'Este correo electrónico ya está registrado'], 409);
        }

        $dniExistente = $em->getRepository(Voluntario::class)->findOneBy(['dni' => $dto->dni]);
        if ($dniExistente) {
            return $this->json(['error' => 'El DNI introducido ya está registrado en el sistema'], 409);
        }

        $em->beginTransaction();
        try {
            $usuario = new Usuario();
            $usuario->setCorreo($dto->correo);
            $usuario->setGoogleId($dto->google_id);

            // Si viene de Google, descargamos la imagen localmente
            if ($dto->img_perfil && str_starts_with($dto->img_perfil, 'http')) {
                // El ID aún no existe, pero podemos usar un delay o simplemente persistir el usuario primero
                $em->persist($usuario);
                $em->flush(); // Ahora tenemos ID

                $filename = $this->saveGoogleImage($dto->img_perfil, $usuario->getId(), $uploadsDirectory);
                if ($filename) {
                    $usuario->setImgPerfil($filename);
                }
            } else {
                $em->persist($usuario);
                if ($dto->img_perfil) {
                    $usuario->setImgPerfil($dto->img_perfil);
                }
            }

            $usuario->setEstadoCuenta('Pendiente');

            $rolVoluntario = $rolRepository->findOneBy(['nombre' => 'Voluntario']);
            if (!$rolVoluntario) throw new \Exception("Rol 'Voluntario' no encontrado");
            $usuario->setRol($rolVoluntario);

            $em->persist($usuario);
            $em->flush(); // Necesario para obtener el ID del usuario (dentro de transacción)

            $voluntario = new Voluntario();
            $voluntario->setUsuario($usuario); // Esto ahora asignará el ID correctamente
            $voluntario->setNombre($dto->nombre);
            $voluntario->setApellidos($dto->apellidos);
            $voluntario->setDni($dto->dni);
            $voluntario->setTelefono($dto->telefono);
            $voluntario->setCarnetConducir($dto->carnet_conducir);
            $voluntario->setDescripcion($dto->descripcion);

            try {
                $voluntario->setFechaNac(new \DateTime($dto->fecha_nac));
            } catch (\Exception $e) {
            }

            $curso = $em->getRepository(Curso::class)->find($dto->id_curso_actual);
            if ($curso) $voluntario->setCursoActual($curso);

            $em->persist($voluntario);

            $tipoRepo = $em->getRepository(TipoVoluntariado::class);
            foreach ($dto->preferencias_ids as $tipoId) {
                $tipo = $tipoRepo->find($tipoId);
                if ($tipo) $voluntario->addPreferencia($tipo);
            }

            $idiomaRepo = $em->getRepository(Idioma::class);
            foreach ($dto->idiomas as $idiomaData) {
                $entidadIdioma = $idiomaRepo->find($idiomaData['id_idioma']);
                if ($entidadIdioma) {
                    $vi = new VoluntarioIdioma();
                    $vi->setVoluntario($voluntario);
                    $vi->setIdioma($entidadIdioma);
                    $vi->setNivel($idiomaData['nivel']);
                    $em->persist($vi);
                }
            }

            $em->flush();
            $em->commit();
            $em->refresh($voluntario);

            return $this->json(VoluntarioResponseDTO::fromEntity($voluntario), Response::HTTP_CREATED);
        } catch (\Exception $e) {
            if ($em->getConnection()->isTransactionActive()) {
                $em->rollback();
            }

            $msg = $e->getMessage();
            if (
                str_contains($msg, 'Duplicate') ||
                str_contains($msg, 'UNIQUE') ||
                str_contains($msg, '2601') ||
                str_contains($msg, '2627')
            ) {
                if (str_contains($msg, 'dni') || str_contains($msg, 'DNI')) {
                    return $this->json(['error' => 'El DNI introducido ya está registrado en el sistema'], 409);
                }
                return $this->json(['error' => 'Este correo electrónico ya está registrado'], 409);
            }

            return $this->json(['error' => '[REF_FAIL] Error técnico al registrar: ' . $msg], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ========================================================================
    // 3. GET ONE (GET)
    // ========================================================================
    #[Route('/voluntarios/{id}', name: 'get_voluntario', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Detalle del voluntario', content: new OA\JsonContent(ref: new Model(type: VoluntarioResponseDTO::class)))]
    public function getOne(int $id, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        $usuario = $userRepo->find($id);
        if (!$usuario || $usuario->getDeletedAt()) return $this->json(['error' => 'No encontrado'], 404);

        $voluntario = $em->getRepository(Voluntario::class)->findOneBy(['usuario' => $usuario]);
        if (!$voluntario) return $this->json(['error' => 'Perfil no encontrado'], 404);

        return $this->json(VoluntarioResponseDTO::fromEntity($voluntario), 200);
    }

    // ========================================================================
    // 4. ACTUALIZAR (PUT)
    // ========================================================================
    #[Route('/voluntarios/{id}', name: 'actualizar_voluntario', methods: ['PUT'])]
    #[OA\Parameter(name: 'X-User-Id', in: 'header', required: true, description: 'ID del usuario logueado', schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: VoluntarioUpdateDTO::class)))]
    #[OA\Response(response: 200, description: 'Perfil actualizado', content: new OA\JsonContent(ref: new Model(type: VoluntarioResponseDTO::class)))]
    public function actualizar(
        int $id,
        #[MapRequestPayload] VoluntarioUpdateDTO $dto,
        Request $request,
        UsuarioRepository $usuarioRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        try {
            if (!$this->checkOwner($request, $id)) {
                return $this->json(['error' => 'No tienes permiso para editar este perfil'], Response::HTTP_FORBIDDEN);
            }

            $usuario = $usuarioRepo->find($id);
            if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], 404);

            $voluntario = $em->getRepository(Voluntario::class)->findOneBy(['usuario' => $usuario]);
            if (!$voluntario) return $this->json(['error' => 'Perfil de voluntario no encontrado'], 404);

            $voluntario->setNombre($dto->nombre);
            $voluntario->setApellidos($dto->apellidos);
            $voluntario->setTelefono($dto->telefono);

            if ($dto->descripcion !== null) {
                $voluntario->setDescripcion($dto->descripcion);
            }

            if ($dto->fechaNac) {
                try {
                    $voluntario->setFechaNac(new \DateTime($dto->fechaNac));
                } catch (\Exception $e) {
                }
            }

            if ($dto->carnet_conducir !== null) {
                $voluntario->setCarnetConducir($dto->carnet_conducir);
            }

            if ($dto->id_curso_actual) {
                $curso = $em->getRepository(Curso::class)->find($dto->id_curso_actual);
                if ($curso) {
                    $voluntario->setCursoActual($curso);
                }
            }

            if ($dto->preferencias_ids !== null) {
                foreach ($voluntario->getPreferencias() as $pref) {
                    $voluntario->removePreferencia($pref);
                }

                if (!empty($dto->preferencias_ids)) {
                    $tipoRepo = $em->getRepository(TipoVoluntariado::class);
                    foreach ($dto->preferencias_ids as $idTipo) {
                        $tipo = $tipoRepo->find($idTipo);
                        if ($tipo) $voluntario->addPreferencia($tipo);
                    }
                }
            }

            if ($dto->idiomas !== null) {
                // 1. Limpiar colección (orphanRemoval se encarga del borrado físico)
                $voluntario->getVoluntarioIdiomas()->clear();
                $em->flush(); // Forzamos el borrado para evitar colisiones de PK

                // 2. Añadir nuevos
                $idiomaRepo = $em->getRepository(Idioma::class);
                foreach ($dto->idiomas as $idiomaData) {
                    $entidadIdioma = $idiomaRepo->find($idiomaData['id_idioma']);
                    if ($entidadIdioma) {
                        $vi = new VoluntarioIdioma();
                        $vi->setVoluntario($voluntario);
                        $vi->setIdioma($entidadIdioma);
                        $vi->setNivel($idiomaData['nivel']);

                        $voluntario->addVoluntarioIdioma($vi);
                        $em->persist($vi);
                    }
                }
            }

            $em->flush();
            $em->refresh($voluntario); // <-- CRITICAL: Sincroniza la colección de idiomas antes de devolver el JSON

            return $this->json(VoluntarioResponseDTO::fromEntity($voluntario), 200);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Error al actualizar perfil',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    // ========================================================================
    // 5. INSCRIBIRSE A ACTIVIDAD (POST)
    // ========================================================================
    #[Route('/voluntarios/{id}/actividades/{idActividad}', name: 'inscribirse_actividad', methods: ['POST'])]
    #[OA\Parameter(name: 'X-User-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 201, description: 'Inscripción realizada')]
    #[OA\Response(response: 409, description: 'Error de reglas de negocio (cupo, fechas, duplicado)')]
    public function inscribirse(
        int $id,
        int $idActividad,
        Request $request,
        UsuarioRepository $userRepo,
        ActividadRepository $actRepo,
        EntityManagerInterface $em
    ): JsonResponse {

        if (!$this->checkOwner($request, $id)) return $this->json(['error' => 'Acceso denegado'], 403);

        $usuario = $userRepo->find($id);
        $voluntario = ($usuario) ? $em->getRepository(Voluntario::class)->findOneBy(['usuario' => $usuario]) : null;
        if (!$voluntario) return $this->json(['error' => 'Voluntario no encontrado'], 404);

        $actividad = $actRepo->find($idActividad);
        if (!$actividad) return $this->json(['error' => 'Actividad no encontrada'], 404);

        $inscripcion = new Inscripcion();
        $inscripcion->setVoluntario($voluntario);
        $inscripcion->setActividad($actividad);
        $inscripcion->setEstadoSolicitud('Pendiente');
        $inscripcion->setFechaSolicitud(new \DateTime());

        try {
            $em->persist($inscripcion);
            $em->flush();
            return $this->json(['mensaje' => 'Inscripción solicitada correctamente'], 201);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'ERROR')) {
                $parts = explode('ERROR', $msg);
                $cleanMsg = isset($parts[1]) ? 'ERROR' . $parts[1] : 'No se pudo realizar la inscripción por reglas de negocio.';
                return $this->json(['error' => trim($cleanMsg)], 409);
            }
            return $this->json(['error' => 'No se pudo realizar la inscripción'], 500);
        }
    }

    // ========================================================================
    // 6. HISTORIAL DE INSCRIPCIONES Y ESTADÍSTICAS (GET)
    // ========================================================================
    #[Route('/voluntarios/{id}/historial', name: 'historial_voluntario', methods: ['GET'])]
    #[OA\Parameter(name: 'X-User-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Historial detallado y resumen',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'resumen', type: 'object'),
            new OA\Property(property: 'actividades', type: 'array', items: new OA\Items(ref: new Model(type: InscripcionResponseDTO::class)))
        ])
    )]
    public function historial(
        int $id,
        Request $request,
        UsuarioRepository $userRepo,
        EntityManagerInterface $em
    ): JsonResponse {

        if (!$this->checkOwner($request, $id)) return $this->json(['error' => 'Acceso denegado'], 403);

        $usuario = $userRepo->find($id);
        $voluntario = ($usuario) ? $em->getRepository(Voluntario::class)->findOneBy(['usuario' => $usuario]) : null;
        if (!$voluntario) return $this->json(['error' => 'Voluntario no encontrado'], 404);

        $inscripciones = $em->getRepository(Inscripcion::class)->findBy(['voluntario' => $voluntario], ['fechaSolicitud' => 'DESC']);

        $horasTotales = 0;
        $participacionesConfirmadas = 0;
        foreach ($inscripciones as $insc) {
            if (in_array($insc->getEstadoSolicitud(), ['Aceptada', 'Finalizada'])) {
                $participacionesConfirmadas++;
                $horasTotales += $insc->getActividad()->getDuracionHoras();
            }
        }

        $actividadesDTOs = [];

        foreach ($inscripciones as $ins) {
            $actividadesDTOs[] = InscripcionResponseDTO::fromEntity($ins);
        }

        return $this->json([
            'resumen' => [
                'total_participaciones' => $participacionesConfirmadas,
                'horas_acumuladas' => $horasTotales,
                'nivel_experiencia' => $horasTotales > 50 ? 'Experto' : ($horasTotales > 20 ? 'Intermedio' : 'Principiante')
            ],
            'actividades' => $actividadesDTOs
        ], 200);
    }

    // ========================================================================
    // 7. DESAPUNTARSE (DELETE)
    // ========================================================================
    #[Route('/voluntarios/{id}/actividades/{idActividad}', name: 'desapuntarse_actividad', methods: ['DELETE'])]
    #[OA\Parameter(name: 'X-User-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Te has desapuntado correctamente')]
    #[OA\Response(response: 404, description: 'No estas inscrito en esta actividad')]
    public function desapuntarse(
        int $id,
        int $idActividad,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {

        if (!$this->checkOwner($request, $id)) return $this->json(['error' => 'Acceso denegado'], 403);

        $voluntarioRef = $em->getReference(Voluntario::class, $id);

        $inscripcion = $em->getRepository(Inscripcion::class)->findOneBy([
            'voluntario' => $voluntarioRef,
            'actividad' => $idActividad
        ]);

        if (!$inscripcion) {
            return $this->json(['error' => 'No estás inscrito en esta actividad'], 404);
        }

        $em->remove($inscripcion);
        $em->flush();

        return $this->json(['mensaje' => 'Te has desapuntado correctamente'], 200);
    }

    // ========================================================================
    // 8. RECOMENDACIONES (GET)
    // ========================================================================
    #[Route('/voluntarios/{id}/recomendaciones', name: 'recomendaciones_voluntario', methods: ['GET'])]
    #[OA\Parameter(name: 'X-User-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Lista de actividades recomendadas',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'id_actividad', type: 'integer', example: 12),
                    new OA\Property(property: 'titulo', type: 'string', example: 'Actividad Recomendada'),
                    new OA\Property(property: 'compatibilidad', type: 'integer', example: 100),
                    new OA\Property(property: 'motivo', type: 'string', example: 'Coincide con tus ODS y disponibilidad')
                ]
            )
        )
    )]
    public function recomendaciones(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->checkOwner($request, $id)) return $this->json(['error' => 'Acceso denegado'], 403);

        $conn = $em->getConnection();
        $sql = 'EXEC SP_Get_Recomendaciones_Voluntario @id_voluntario = :id';
        try {
            $actividades = $conn->executeQuery($sql, ['id' => $id])->fetchAllAssociative();
            return $this->json($actividades, 200);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al calcular recomendaciones'], 500);
        }
    }

    // ========================================================================
    // 9. HORAS TOTALES DE VOLUNTARIADO (GET)
    // ========================================================================
    #[Route('/voluntarios/{id}/horas-totales', name: 'horas_totales_voluntario', methods: ['GET'])]
    #[OA\Parameter(name: 'X-User-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Total de horas de voluntariado realizadas (campo calculado)',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'horas_totales', type: 'integer', example: 45, description: 'Suma total de horas de actividades Aceptadas o Finalizadas')
            ]
        )
    )]
    public function horasTotales(int $id, Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->checkOwner($request, $id)) {
            return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);
        }

        $usuario = $userRepo->find($id);
        if (!$usuario || $usuario->getDeletedAt()) {
            return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $voluntario = $em->getRepository(Voluntario::class)->findOneBy(['usuario' => $usuario]);
        if (!$voluntario) {
            return $this->json(['error' => 'Perfil de voluntario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $conn = $em->getConnection();

        $sql = "
            SELECT COALESCE(SUM(a.duracion_horas), 0) as horas_totales
            FROM INSCRIPCION i
            INNER JOIN ACTIVIDAD a ON i.id_actividad = a.id_actividad
            WHERE i.id_voluntario = :id_voluntario
            AND i.estado_solicitud IN ('Aceptada', 'Finalizada')
            AND a.deleted_at IS NULL
        ";

        try {
            $result = $conn->executeQuery($sql, ['id_voluntario' => $id])->fetchAssociative();
            $horasTotales = (int)$result['horas_totales'];

            return $this->json([
                'horas_totales' => $horasTotales
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al calcular horas totales'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    private function saveGoogleImage(string $url, int $userId, string $uploadsDirectory): ?string
    {
        try {
            $opts = [
                'http' => [
                    'method' => 'GET',
                    'header' => "User-Agent: PHP\r\n"
                ]
            ];
            $context = stream_context_create($opts);
            $content = @file_get_contents($url, false, $context);
            if ($content === false) return null;

            $filename = 'google_' . $userId . '_' . uniqid() . '.jpg';
            $targetDir = $uploadsDirectory . '/usuarios';
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            file_put_contents($targetDir . '/' . $filename, $content);
            return $filename;
        } catch (\Exception $e) {
            return null;
        }
    }
}
