<?php

namespace App\Controller;

use App\Entity\Actividad;
use App\Entity\Coordinador;
use App\Entity\Usuario;
use App\Model\Coordinador\CoordinadorCreateDTO;
use App\Model\Coordinador\CoordinadorResponseDTO;
use App\Model\Coordinador\CoordinadorUpdateDTO;
use App\Repository\ActividadRepository;
use App\Repository\RolRepository;
use App\Repository\UsuarioRepository;
use App\Repository\CoordinadorRepository;
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
#[OA\Tag(name: 'Coordinadores', description: 'Gestión interna, Dashboard y Moderación Global')]
final class CoordinadorController extends AbstractController
{
    private function checkCoordinador(Request $request, UsuarioRepository $repo): ?Usuario
    {
        $adminId = $request->headers->get('X-Admin-Id');
        if (!$adminId) return null;

        $user = $repo->find($adminId);
        if ($user && in_array($user->getRol()->getNombre(), ['Coordinador'])) {
            return $user;
        }
        return null;
    }

    // ========================================================================
    // 1. DASHBOARD GLOBAL (GET)
    // ========================================================================
    #[Route('/coord/stats', name: 'coord_stats', methods: ['GET'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', description: 'ID de Coordinador', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Métricas del dashboard global',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'titulo', type: 'string'),
            new OA\Property(property: 'fecha_generacion', type: 'string'),
            new OA\Property(property: 'metricas', properties: [
                new OA\Property(property: 'voluntarios_activos', type: 'integer'),
                new OA\Property(property: 'organizaciones_activas', type: 'integer'),
                new OA\Property(property: 'coordinadores_activos', type: 'integer'),
                new OA\Property(property: 'actividades_publicadas', type: 'integer'),
                new OA\Property(property: 'voluntarios_pendientes', type: 'integer'),
                new OA\Property(property: 'actividades_pendientes', type: 'integer')
            ], type: 'object')
        ])
    )]
    public function dashboard(
        Request $request,
        UsuarioRepository $userRepo,
        EntityManagerInterface $em
    ): JsonResponse {

        if (!$this->checkCoordinador($request, $userRepo)) {
            return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);
        }

        $conn = $em->getConnection();

        try {
            $rawStats = [];
            try {
                $rawStats = $conn->executeQuery('EXEC SP_Dashboard_Stats')->fetchAssociative();
            } catch (\Exception $e) {
                $rawStats = [
                    'voluntarios_activos' => $conn->fetchOne("SELECT COUNT(*) FROM USUARIO u JOIN ROL r ON u.id_rol = r.id_rol WHERE UPPER(r.nombre_rol) = 'VOLUNTARIO' AND u.deleted_at IS NULL"),
                    'organizaciones_activas' => $conn->fetchOne("SELECT COUNT(*) FROM USUARIO u JOIN ROL r ON u.id_rol = r.id_rol WHERE UPPER(r.nombre_rol) = 'ORGANIZACION' AND u.deleted_at IS NULL"),
                    'coordinadores_activos' => $conn->fetchOne("SELECT COUNT(*) FROM USUARIO u JOIN ROL r ON u.id_rol = r.id_rol WHERE UPPER(r.nombre_rol) = 'COORDINADOR' AND u.deleted_at IS NULL"),
                    'actividades_publicadas' => $conn->fetchOne("SELECT COUNT(*) FROM ACTIVIDAD WHERE UPPER(estado_publicacion) = 'PUBLICADA' AND deleted_at IS NULL"),
                    'voluntarios_pendientes' => $conn->fetchOne("SELECT COUNT(*) FROM USUARIO WHERE UPPER(estado_cuenta) = 'PENDIENTE' AND deleted_at IS NULL"),
                    'actividades_pendientes' => $conn->fetchOne("SELECT COUNT(*) FROM ACTIVIDAD WHERE UPPER(estado_publicacion) IN ('EN REVISION', 'PENDIENTE') AND deleted_at IS NULL")
                ];
            }

            $safeStats = [
                'voluntarios_activos'    => (int)($rawStats['voluntarios_activos'] ?? $rawStats['total_usuarios'] ?? 0),
                'organizaciones_activas' => (int)($rawStats['organizaciones_activas'] ?? $rawStats['total_organizaciones'] ?? 0),
                'coordinadores_activos'  => (int)($rawStats['coordinadores_activos'] ?? $conn->fetchOne("SELECT COUNT(*) FROM USUARIO u JOIN ROL r ON u.id_rol = r.id_rol WHERE r.nombre_rol = 'Coordinador' AND u.deleted_at IS NULL")),
                'actividades_publicadas' => (int)($rawStats['actividades_publicadas'] ?? 0),
                'voluntarios_pendientes' => (int)($rawStats['voluntarios_pendientes'] ?? $rawStats['inscripciones_pendientes'] ?? 0),
                'actividades_pendientes' => (int)($rawStats['actividades_pendientes'] ?? $rawStats['actividades_revision'] ?? 0),
            ];

            return $this->json([
                'titulo' => 'Panel de Control General',
                'fecha_generacion' => date('Y-m-d H:i:s'),
                'metricas' => $safeStats
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error calculando estadísticas: ' . $e->getMessage()], 500);
        }
    }

    // ========================================================================
    // 2. REGISTRAR COORDINADOR (POST)
    // ========================================================================
    #[Route('/coordinadores', name: 'registro_coordinador', methods: ['POST'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: CoordinadorCreateDTO::class)))]
    public function registrar(
        Request $request,
        #[MapRequestPayload] CoordinadorCreateDTO $dto,
        EntityManagerInterface $entityManager,
        RolRepository $rolRepository,
        UsuarioRepository $userRepo
    ): JsonResponse {

        if (!$this->checkCoordinador($request, $userRepo)) {
            return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);
        }

        $entityManager->beginTransaction();
        try {
            $usuario = new Usuario();
            $usuario->setCorreo($dto->correo);
            $usuario->setGoogleId($dto->google_id);
            $usuario->setEstadoCuenta('Activa');

            $rolCoord = $rolRepository->findOneBy(['nombre' => 'Coordinador']);
            if (!$rolCoord) throw new \Exception("Rol 'Coordinador' no configurado.");
            $usuario->setRol($rolCoord);

            $entityManager->persist($usuario);
            $entityManager->flush();

            $coord = new Coordinador();
            $coord->setUsuario($usuario);
            $coord->setNombre($dto->nombre);
            $coord->setApellidos($dto->apellidos);
            $coord->setTelefono($dto->telefono);

            $entityManager->persist($coord);
            $entityManager->flush();
            $entityManager->commit();

            return $this->json(CoordinadorResponseDTO::fromEntity($coord), Response::HTTP_CREATED);
        } catch (\Exception $e) {
            $entityManager->rollback();
            return $this->json(['error' => 'Error al registrar: ' . $e->getMessage()], 500);
        }
    }

    // ========================================================================
    // 3. VER PERFIL COORDINADOR (GET)
    // ========================================================================
    #[Route('/coordinadores/{id}', name: 'get_coordinador', methods: ['GET'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function getOne(int $id, Request $request, UsuarioRepository $userRepo, CoordinadorRepository $coordRepo): JsonResponse
    {
        if (!$this->checkCoordinador($request, $userRepo)) return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);

        $usuario = $userRepo->find($id);
        if (!$usuario || $usuario->getDeletedAt()) return $this->json(['error' => 'Coordinador no encontrado'], Response::HTTP_NOT_FOUND);

        $coord = $coordRepo->findOneBy(['usuario' => $usuario]);
        if (!$coord) {
            return $this->json([
                'id_usuario' => $usuario->getId(),
                'nombre' => 'Usuario',
                'apellidos' => $usuario->getId(),
                'telefono' => '',
                'correo' => $usuario->getCorreo()
            ], Response::HTTP_OK);
        }
        return $this->json(CoordinadorResponseDTO::fromEntity($coord), Response::HTTP_OK);
    }

    // ========================================================================
    // 4. ACTUALIZAR PERFIL COORDINADOR (PUT)
    // ========================================================================
    #[Route('/coordinadores/{id}', name: 'actualizar_coordinador', methods: ['PUT'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: CoordinadorUpdateDTO::class)))]
    public function actualizar(int $id, #[MapRequestPayload] CoordinadorUpdateDTO $dto, Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->checkCoordinador($request, $userRepo)) return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);

        $usuario = $userRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);

        $coord = $em->getRepository(Coordinador::class)->findOneBy(['usuario' => $usuario]);
        if (!$coord) return $this->json(['error' => 'Perfil no encontrado'], Response::HTTP_NOT_FOUND);

        $coord->setNombre($dto->nombre);
        $coord->setApellidos($dto->apellidos);
        $coord->setTelefono($dto->telefono);
        $coord->setUpdatedAt(new \DateTime());
        $em->flush();

        return $this->json(CoordinadorResponseDTO::fromEntity($coord), Response::HTTP_OK);
    }

    // ========================================================================
    // 5. GESTIÓN DE ESTADO DE USUARIOS (PATCH)
    // ========================================================================
    #[Route('/coord/voluntarios/pendientes', name: 'coord_voluntarios_pendientes', methods: ['GET'])]
    public function listarVoluntariosPendientes(Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->checkCoordinador($request, $userRepo)) return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);

        $baseUrl = $request->getSchemeAndHttpHost();
        $conn = $em->getConnection();
        $sql = "
            SELECT
                u.id_usuario, u.correo, u.estado_cuenta,
                CASE
                    WHEN u.img_perfil IS NULL OR u.img_perfil = '' THEN NULL
                    WHEN u.img_perfil LIKE 'http%' THEN u.img_perfil
                    ELSE :base_url + '/uploads/usuarios/' + u.img_perfil
                END as img_perfil,
                v.nombre, v.apellidos, v.dni, v.telefono, v.fecha_nac, v.carnet_conducir, v.descripcion,
                c.nombre_curso as curso,
                (SELECT STRING_AGG(t.nombre_tipo, ', ')
                 FROM PREFERENCIA_VOLUNTARIO pv
                 JOIN TIPO_VOLUNTARIADO t ON pv.id_tipo = t.id_tipo
                 WHERE pv.id_voluntario = u.id_usuario) as intereses,
                (SELECT STRING_AGG(idi.nombre_idioma + ' (' + vi.nivel + ')', ', ')
                 FROM VOLUNTARIO_IDIOMA vi
                 JOIN IDIOMA idi ON vi.id_idioma = idi.id_idioma
                 WHERE vi.id_voluntario = u.id_usuario) as idiomas
            FROM USUARIO u
            JOIN VOLUNTARIO v ON u.id_usuario = v.id_usuario
            LEFT JOIN CURSO c ON v.id_curso_actual = c.id_curso
            WHERE UPPER(u.estado_cuenta) = 'PENDIENTE' AND u.deleted_at IS NULL
        ";
        $data = $conn->executeQuery($sql, ['base_url' => $baseUrl])->fetchAllAssociative();
        return $this->json($data, Response::HTTP_OK);
}

    #[Route('/coord/organizaciones/pendientes', name: 'coord_organizaciones_pendientes', methods: ['GET'])]
    public function listarOrganizacionesPendientes(Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->checkCoordinador($request, $userRepo)) return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);

        $baseUrl = $request->getSchemeAndHttpHost();
        $conn = $em->getConnection();
        $sql = "
            SELECT
                u.id_usuario, u.correo, u.estado_cuenta,
                CASE
                    WHEN u.img_perfil IS NULL OR u.img_perfil = '' THEN NULL
                    WHEN u.img_perfil LIKE 'http%' THEN u.img_perfil
                    ELSE :base_url + '/uploads/usuarios/' + u.img_perfil
                END as img_perfil,
                o.nombre, o.cif, o.telefono, o.sitio_web, o.direccion, o.descripcion
            FROM USUARIO u
            JOIN ORGANIZACION o ON u.id_usuario = o.id_usuario
            WHERE UPPER(u.estado_cuenta) = 'PENDIENTE' AND u.deleted_at IS NULL
        ";
        return $this->json($conn->executeQuery($sql, ['base_url' => $baseUrl])->fetchAllAssociative(), Response::HTTP_OK);
}

    #[Route('/coord/{rol}/{id}/estado', name: 'coord_cambiar_estado_usuario', methods: ['PATCH'], requirements: ['rol' => 'voluntarios|organizaciones'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function cambiarEstadoUsuario(int $id, string $rol, Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->checkCoordinador($request, $userRepo)) return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);

        $data = json_decode($request->getContent(), true);
        $nuevoEstado = $data['estado'] ?? null;

        $usuario = $userRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);

        $usuario->setEstadoCuenta($nuevoEstado);
        $usuario->setUpdatedAt(new \DateTime());
        $em->flush();

        return $this->json(['mensaje' => 'Estado actualizado a ' . $nuevoEstado], Response::HTTP_OK);
    }

    // ========================================================================
    // 6. LISTAR ACTIVIDADES GLOBALES (GET)
    // ========================================================================
    #[Route('/coord/actividades', name: 'coord_listar_actividades', methods: ['GET'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function listarActividadesGlobal(Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->checkCoordinador($request, $userRepo)) return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);

        $baseUrl = $request->getSchemeAndHttpHost();
        $conn = $em->getConnection();
        $sql = "
            SELECT
                a.id_actividad, a.titulo, a.descripcion, a.fecha_inicio, a.duracion_horas,
                a.cupo_maximo, a.ubicacion, a.estado_publicacion,
                CASE
                    WHEN a.img_actividad IS NULL OR a.img_actividad = '' THEN NULL
                    ELSE :base_url + '/uploads/actividades/' + a.img_actividad
                END as imagen_actividad,
                (SELECT COUNT(*) FROM INSCRIPCION i WHERE i.id_actividad = a.id_actividad AND i.estado_solicitud = 'Aceptada') as inscritos_confirmados,
                COALESCE(o.nombre, 'Organización Desconocida') as nombre_organizacion,
                COALESCE(u.id_usuario, 0) as id_organizacion
            FROM ACTIVIDAD a
            LEFT JOIN ORGANIZACION o ON a.id_organizacion = o.id_usuario
            LEFT JOIN USUARIO u ON o.id_usuario = u.id_usuario
            WHERE (a.deleted_at IS NULL OR a.estado_publicacion IN ('Cancelada', 'Rechazada'))
            ORDER BY a.fecha_inicio DESC
        ";

        try {
            $actividades = $conn->executeQuery($sql, ['base_url' => $baseUrl])->fetchAllAssociative();
            return $this->json($actividades, Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error: ' . $e->getMessage()], 500);
        }
}

    #[Route('/coord/actividades/pendientes', name: 'coord_actividades_pendientes', methods: ['GET'])]
    public function listarActividadesPendientes(Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->checkCoordinador($request, $userRepo)) return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);

        $baseUrl = $request->getSchemeAndHttpHost();
        $conn = $em->getConnection();
        $sql = "
            SELECT
                a.id_actividad, a.titulo, a.descripcion, a.fecha_inicio, a.duracion_horas,
                a.cupo_maximo, a.ubicacion, a.estado_publicacion,
                CASE
                    WHEN a.img_actividad IS NULL OR a.img_actividad = '' THEN NULL
                    ELSE :base_url + '/uploads/actividades/' + a.img_actividad
                END as imagen_actividad,
                o.nombre as nombre_organizacion,
                u.correo as email_organizacion,
                CASE
                    WHEN u.img_perfil IS NULL OR u.img_perfil = '' THEN NULL
                    WHEN u.img_perfil LIKE 'http%' THEN u.img_perfil
                    ELSE :base_url + '/uploads/usuarios/' + u.img_perfil
                END as img_perfil_organizacion
            FROM ACTIVIDAD a
            JOIN ORGANIZACION o ON a.id_organizacion = o.id_usuario
            JOIN USUARIO u ON o.id_usuario = u.id_usuario
            WHERE UPPER(a.estado_publicacion) IN ('EN REVISION', 'PENDIENTE') AND a.deleted_at IS NULL
            ORDER BY a.fecha_inicio ASC
        ";
        return $this->json($conn->executeQuery($sql, ['base_url' => $baseUrl])->fetchAllAssociative(), Response::HTTP_OK);
}

    // ========================================================================
    // 7. CAMBIAR ESTADO ACTIVIDAD (PATCH)
    // ========================================================================
    #[Route('/coord/actividades/{id}/estado', name: 'coord_cambiar_estado_actividad', methods: ['PATCH'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function cambiarEstadoActividad(int $id, Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->checkCoordinador($request, $userRepo)) return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);

        $data = json_decode($request->getContent(), true);
        $actividad = $em->getRepository(Actividad::class)->find($id);
        if (!$actividad) return $this->json(['error' => 'Actividad no encontrada'], Response::HTTP_NOT_FOUND);

        $actividad->setEstadoPublicacion($data['estado']);
        $actividad->setUpdatedAt(new \DateTime());
        $em->flush();

        return $this->json(['mensaje' => 'Estado actualizado'], Response::HTTP_OK);
    }

    // ========================================================================
    // 8. BORRAR ACTIVIDAD (DELETE)
    // ========================================================================
    #[Route('/coord/actividades/{id}', name: 'coord_borrar_actividad', methods: ['DELETE'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function borrarActividadCoord(int $id, Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->checkCoordinador($request, $userRepo)) return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);

        $sql = 'EXEC SP_SoftDelete_Actividad @id_actividad = :id';
        $em->getConnection()->executeStatement($sql, ['id' => $id]);
        return $this->json(['mensaje' => 'Actividad eliminada'], Response::HTTP_OK);
    }

    // ========================================================================
    // 9. EDITAR ACTIVIDAD (PUT)
    // ========================================================================
    // ========================================================================
    // 10. LISTAR INSCRIPCIONES PENDIENTES (GET)
    // ========================================================================
    #[Route('/coord/inscripciones/pendientes', name: 'coord_listar_inscripciones', methods: ['GET'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function listarInscripcionesPendientes(
        Request $request,
        UsuarioRepository $userRepo,
        EntityManagerInterface $em
    ): JsonResponse
    {
        file_put_contents(__DIR__ . '/../../var/debug_entry.log', "Entrando en metodo listarInscripcionesPendientes " . date('H:i:s') . "\n", FILE_APPEND);

        try {
            if (!$this->checkCoordinador($request, $userRepo)) return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);

            // Usamos Doctrine para traer las Entidades completas logueando paso a paso
            $inscripcionRepo = $em->getRepository(\App\Entity\Inscripcion::class);
            $inscripciones = $inscripcionRepo->findBy(['estadoSolicitud' => 'Pendiente'], ['fechaSolicitud' => 'ASC']);

            file_put_contents(__DIR__ . '/../../var/debug_entry.log', "Inscripciones encontradas: " . count($inscripciones) . "\n", FILE_APPEND);

            $data = [];
            foreach ($inscripciones as $ins) {
                $voluntario = $ins->getVoluntario();
                if (!$voluntario) continue;

                $usuarioVol = $voluntario->getUsuario();
                if (!$usuarioVol) continue;

                $actividad = $ins->getActividad();
                if (!$actividad) continue;

                // Recopilar idiomas
                $idiomas = [];
                /*
                   Posible punto de fallo si getVoluntarioIdiomas no devuelve colección iterable
                   o si los objetos internos no tienen los métodos esperados.
                */
                foreach ($voluntario->getVoluntarioIdiomas() as $vi) {
                    $nombreIdioma = 'Desconocido';
                    if ($vi->getIdioma()) {
                        $nombreIdioma = $vi->getIdioma()->getNombre();
                    }
                    $idiomas[] = [
                        'idioma' => $nombreIdioma,
                        'nivel' => $vi->getNivel()
                    ];
                }

                // Recopilar preferencias (intereses)
                $preferencias = [];
                foreach ($voluntario->getPreferencias() as $pref) {
                    $preferencias[] = $pref->getNombreTipo();
                }

                $baseUrl = $request->getSchemeAndHttpHost();
                $imgPerfil = $usuarioVol->getImgPerfil();
                $imgPerfilUrl = null;
                if ($imgPerfil) {
                    if (str_starts_with($imgPerfil, 'http')) {
                        $imgPerfilUrl = $imgPerfil;
                    } else {
                        $imgPerfilUrl = $baseUrl . '/uploads/usuarios/' . $imgPerfil;
                    }
                }

                $imgActividad = $actividad->getImgActividad();
                $imgActividadUrl = null;
                if ($imgActividad) {
                    if (str_starts_with($imgActividad, 'http')) {
                       $imgActividadUrl = $imgActividad;
                    } else {
                       $imgActividadUrl = $baseUrl . '/uploads/actividades/' . $imgActividad;
                    }
                }

                // Calcular fecha fin
                $fechaFin = null;
                if ($actividad->getFechaInicio() && $actividad->getDuracionHoras()) {
                    $fechaFin = (clone $actividad->getFechaInicio())->modify('+' . $actividad->getDuracionHoras() . ' hours');
                }

                // Obtener tipos de voluntariado (ManyToMany)
                $tipos = [];
                foreach($actividad->getTiposVoluntariado() as $t) {
                    $tipos[] = $t->getNombreTipo();
                }
                $tipoString = !empty($tipos) ? implode(', ', $tipos) : 'General';

                // Obtener datos de la organización
                $organizacion = $actividad->getOrganizacion();
                $orgData = null;

                if ($organizacion) {
                    $usuarioOrg = $organizacion->getUsuario();
                    if ($usuarioOrg) {
                        $imgOrg = $usuarioOrg->getImgPerfil();
                        $imgOrgUrl = null;
                        if ($imgOrg) {
                            if (str_starts_with($imgOrg, 'http')) {
                                $imgOrgUrl = $imgOrg;
                            } else {
                                $imgOrgUrl = $baseUrl . '/uploads/usuarios/' . $imgOrg;
                            }
                        }

                        $orgData = [
                            'id' => $organizacion->getId(),
                            'nombre' => $organizacion->getNombre(),
                            'email' => $usuarioOrg->getCorreo(),
                            'direccion' => $organizacion->getDireccion(),
                            'img_url' => $imgOrgUrl
                        ];
                    }
                }

                // Contar inscritos confirmados
                $countInscritosConfirmados = 0;
                foreach($actividad->getInscripciones() as $i) {
                     if ($i->getEstadoSolicitud() === 'Aceptada') {
                         $countInscritosConfirmados++;
                     }
                }

                // Construir objeto respuesta
                $data[] = [
                    'id_actividad' => $actividad->getId(),
                    'id_voluntario' => $voluntario->getId(),
                    'fecha_solicitud' => $ins->getFechaSolicitud()->format('c'), // ISO8601
                    'estado_solicitud' => $ins->getEstadoSolicitud(),

                    // --- DATOS DEL VOLUNTARIO COMPLETO ---
                    'voluntario' => [
                        'id' => $voluntario->getId(),
                        'nombre' => $voluntario->getNombre(),
                        'apellidos' => $voluntario->getApellidos(),
                        'email' => $usuarioVol->getCorreo(),
                        'dni' => $voluntario->getDni(),
                        'telefono' => $voluntario->getTelefono(),
                        'fecha_nacimiento' => $voluntario->getFechaNac() ? $voluntario->getFechaNac()->format('Y-m-d') : null,
                        'descripcion' => $voluntario->getDescripcion(),
                        'carnet_conducir' => $voluntario->isCarnetConducir(),
                        'curso' => $voluntario->getCursoActual() ? $voluntario->getCursoActual()->getNombre() : 'Sin curso',
                        'img_perfil_url' => $imgPerfilUrl,
                        'idiomas' => $idiomas,
                        'preferencias' => $preferencias
                    ],

                    // --- DATOS BÁSICOS PARA LA TABLA (COMPATIBILIDAD) ---
                    'nombre_voluntario' => $voluntario->getNombre(),
                    'apellidos_voluntario' => $voluntario->getApellidos(),
                    'email_voluntario' => $usuarioVol->getCorreo(),

                    // --- DATOS DE LA ACTIVIDAD ---
                    'actividad' => [
                        'id' => $actividad->getId(),
                        'titulo' => $actividad->getTitulo(),
                        'descripcion' => $actividad->getDescripcion(),
                        'fecha_inicio' => $actividad->getFechaInicio()->format('c'),
                        'fecha_fin' => $fechaFin ? $fechaFin->format('c') : null,
                        'ubicacion' => $actividad->getUbicacion(),
                        'tipo' => $tipoString,
                        'img_actividad_url' => $imgActividadUrl,
                        'organizacion' => $orgData,
                        'cupo_maximo' => $actividad->getCupoMaximo(), // <--- NUEVO
                        'inscritos_confirmados' => $countInscritosConfirmados, // <--- NUEVO
                         // Estos son para compatibilidad con la tabla actual
                        'titulo_actividad' => $actividad->getTitulo(),
                    ],
                    'titulo_actividad' => $actividad->getTitulo(), // Compatibilidad directa
                    'imagen_actividad' => $actividad->getImgActividad() // Compatibilidad directa
                ];
            }

            return $this->json($data, Response::HTTP_OK);

        } catch (\Throwable $e) {
            $logContent = date('Y-m-d H:i:s') . " - Error 500 en listarInscripciones: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n";
            file_put_contents(__DIR__ . '/../../var/debug_500.log', $logContent, FILE_APPEND);
            file_put_contents(__DIR__ . '/../../var/debug_message.log', $e->getMessage()); // Solo mensaje para leer fácil

            return $this->json([
                'error' => 'Error al listar inscripciones: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    #[Route('/coord/actividades/{id}', name: 'coord_editar_actividad', methods: ['PUT'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function editarActividadCoord(
        int $id,
        Request $request,
        ActividadRepository $repo,
        UsuarioRepository $userRepo,
        EntityManagerInterface $em,
        \App\Repository\ODSRepository $odsRepo,
        \App\Repository\TipoVoluntariadoRepository $tipoRepo
    ): JsonResponse
    {
        if (!$this->checkCoordinador($request, $userRepo)) return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);

        $actividad = $repo->find($id);
        if (!$actividad) return $this->json(['error' => 'No encontrada'], 404);

        $data = json_decode($request->getContent(), true);

        // Campos básicos
        if (isset($data['titulo'])) $actividad->setTitulo($data['titulo']);
        if (isset($data['descripcion'])) $actividad->setDescripcion($data['descripcion']);
        if (isset($data['cupo_maximo'])) $actividad->setCupoMaximo($data['cupo_maximo']);
        if (isset($data['ubicacion'])) $actividad->setUbicacion($data['ubicacion']);
        if (isset($data['fecha_inicio'])) $actividad->setFechaInicio(new \DateTime($data['fecha_inicio']));
        if (isset($data['duracion_horas'])) $actividad->setDuracionHoras($data['duracion_horas']);

        // Actualizar ODS
        if (isset($data['odsIds']) && is_array($data['odsIds'])) {
            // Eliminar ODS existentes
            foreach ($actividad->getOds() as $odExisting) {
                $actividad->removeOd($odExisting);
            }
            // Agregar nuevos ODS
            foreach ($data['odsIds'] as $idOds) {
                $ods = $odsRepo->find($idOds);
                if ($ods) $actividad->addOd($ods);
            }
        }

        // Actualizar Tipos de Voluntariado
        if (isset($data['tiposIds']) && is_array($data['tiposIds'])) {
            // Eliminar tipos existentes
            foreach ($actividad->getTiposVoluntariado() as $tipoExisting) {
                $actividad->removeTiposVoluntariado($tipoExisting);
            }
            // Agregar nuevos tipos
            foreach ($data['tiposIds'] as $idTipo) {
                $tipo = $tipoRepo->find($idTipo);
                if ($tipo) $actividad->addTiposVoluntariado($tipo);
            }
        }

        $em->flush();

        $baseUrl = $request->getSchemeAndHttpHost();
        return $this->json([
            'mensaje' => 'Actividad actualizada correctamente',
            'actividad' => \App\Model\Actividad\ActividadResponseDTO::fromEntity($actividad, $baseUrl)
        ], 200);
    }

    #[Route('/coord/actividades/{id}/detalle', name: 'coord_actividad_detalle', methods: ['GET'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function getActividadDetalleCoord(int $id, Request $request, ActividadRepository $repo, UsuarioRepository $userRepo): JsonResponse
    {
        if (!$this->checkCoordinador($request, $userRepo)) return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);

        $actividad = $repo->find($id);
        if (!$actividad) return $this->json(['error' => 'Actividad no encontrada'], 404);

        $baseUrl = $request->getSchemeAndHttpHost();
        return $this->json(\App\Model\Actividad\ActividadResponseDTO::fromEntity($actividad, $baseUrl), 200);
    }

    #[Route('/coord/organizaciones/{id}/detalle', name: 'coord_organizacion_detalle', methods: ['GET'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function getOrganizacionDetalleCoord(int $id, Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->checkCoordinador($request, $userRepo)) return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);

        $organizacion = $em->getRepository(\App\Entity\Organizacion::class)->find($id);
        if (!$organizacion) return $this->json(['error' => 'Organización no encontrada'], 404);

        $baseUrl = $request->getSchemeAndHttpHost();
        return $this->json(\App\Model\Organizacion\OrganizacionResponseDTO::fromEntity($organizacion, $baseUrl), 200);
    }

    // ========================================================================
    // 10. ELIMINAR COORDINADOR (DELETE)
    // ========================================================================
    #[Route('/coordinadores/{id}', name: 'borrar_usuario_coord', methods: ['DELETE'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    public function eliminar(int $id, Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->checkCoordinador($request, $userRepo)) return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);

        $sql = 'EXEC SP_SoftDelete_Usuario @id_usuario = :id';
        $em->getConnection()->executeStatement($sql, ['id' => $id]);
        return $this->json(['mensaje' => 'Cuenta cerrada'], Response::HTTP_OK);
    }
    // ========================================================================
    // 11. CAMBIAR ESTADO USUARIO (PATCH)
    // ========================================================================
    #[Route('/coord/usuarios/{id}/estado', name: 'coord_update_user_status', methods: ['PATCH'])]
    #[OA\Parameter(name: 'X-Admin-Id', in: 'header', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'estado', type: 'string', example: 'Bloqueada')]))]
    public function updateEstadoUsuario(
        int $id,
        Request $request,
        UsuarioRepository $repo,
        EntityManagerInterface $em
    ): JsonResponse {
        if (!$this->checkCoordinador($request, $repo)) {
            return $this->json(['error' => 'Acceso denegado'], Response::HTTP_FORBIDDEN);
        }

        $usuario = $repo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], 404);

        $data = json_decode($request->getContent(), true);
        $nuevoEstado = $data['estado'] ?? null;

        if (!in_array($nuevoEstado, ['Activa', 'Bloqueada', 'Pendiente', 'Rechazada'])) {
            return $this->json(['error' => 'Estado inválido'], 400);
        }

        $usuario->setEstadoCuenta($nuevoEstado);
        $em->flush();

        return $this->json(['mensaje' => "Estado actualizado a $nuevoEstado"], 200);
    }
}
