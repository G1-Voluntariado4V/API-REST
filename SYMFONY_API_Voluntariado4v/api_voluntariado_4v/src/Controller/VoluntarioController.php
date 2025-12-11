<?php

namespace App\Controller;

use App\Entity\Usuario;
use App\Entity\Voluntario;
use App\Entity\VoluntarioIdioma;
use App\Repository\RolRepository;
use App\Repository\CursoRepository;
use App\Repository\IdiomaRepository;
use App\Repository\UsuarioRepository;
use App\Repository\TipoVoluntariadoRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException; // Para duplicados
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response; // Códigos HTTP
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA; // Swagger

#[Route('/api', name: 'api_')]
#[OA\Tag(name: 'Voluntarios', description: 'Gestión de perfiles de voluntarios, recomendaciones y registro')]
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
        // Usamos el SP que cruza preferencias con ODS de actividades
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
    // 3. REGISTRAR VOLUNTARIO (Transaccional)
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
    #[OA\Response(response: 500, description: 'Error en la transacción')]
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

        // INICIO TRANSACCIÓN: O se guarda todo, o no se guarda nada
        $entityManager->beginTransaction();

        try {
            // A. USUARIO BASE
            $usuario = new Usuario();
            $usuario->setCorreo($data['correo']);
            $usuario->setGoogleId($data['google_id']);
            $usuario->setEstadoCuenta('Activa'); 
            
            $rolVoluntario = $rolRepository->findOneBy(['nombre' => 'Voluntario']); 
            // Ojo: Asegúrate de que el rol en BBDD es 'Voluntario' (tu script SQL insertó 'Voluntario')
            if (!$rolVoluntario) throw new \Exception("Error interno: Rol 'Voluntario' no configurado en BBDD");
            $usuario->setRol($rolVoluntario);

            $entityManager->persist($usuario);
            $entityManager->flush(); // Necesario para obtener el ID del usuario

            // B. PERFIL VOLUNTARIO
            $voluntario = new Voluntario();
            $voluntario->setUsuario($usuario); // Relación 1 a 1
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

            // C. IDIOMAS (Tabla Intermedia con atributos)
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

            // D. PREFERENCIAS (Tabla Intermedia simple)
            if (!empty($data['preferencias_ids']) && is_array($data['preferencias_ids'])) {
                foreach ($data['preferencias_ids'] as $idTipo) {
                    $tipo = $tipoRepo->find($idTipo);
                    if ($tipo) $voluntario->addPreferencia($tipo);
                }
            }

            $entityManager->persist($voluntario);
            $entityManager->flush();
            
            // Si todo ha ido bien, confirmamos cambios
            $entityManager->commit();

            return $this->json($voluntario, Response::HTTP_CREATED, [], ['groups' => 'usuario:read']);

        } catch (UniqueConstraintViolationException $e) {
            $entityManager->rollback(); // Deshacemos cambios
            return $this->json(['error' => 'El correo o DNI ya existen'], Response::HTTP_CONFLICT);
        } catch (\Exception $e) {
            $entityManager->rollback(); // Deshacemos cambios
            return $this->json(['error' => 'Error al registrar: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ========================================================================
    // 4. GET ONE
    // ========================================================================
    #[Route('/voluntarios/{id}', name: 'get_voluntario', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Detalle del voluntario')]
    #[OA\Response(response: 404, description: 'No encontrado')]
    public function getOne(int $id, UsuarioRepository $userRepo): JsonResponse 
    {
        $usuario = $userRepo->find($id);
        // Si no existe o tiene deleted_at, devolvemos 404
        if (!$usuario || $usuario->getDeletedAt()) {
            return $this->json(['error' => 'Voluntario no encontrado'], Response::HTTP_NOT_FOUND);
        }
        return $this->json($usuario, Response::HTTP_OK, [], ['groups' => 'usuario:read']);
    }

    // ========================================================================
    // 5. ACTUALIZAR (PUT)
    // ========================================================================
    #[Route('/voluntarios/{id}', name: 'actualizar_voluntario', methods: ['PUT'])]
    #[OA\RequestBody(description: 'Datos a actualizar (parcial)', content: new OA\JsonContent(type: 'object'))]
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

        // Campos básicos
        if (isset($data['nombre'])) $voluntario->setNombre($data['nombre']);
        if (isset($data['apellidos'])) $voluntario->setApellidos($data['apellidos']);
        if (isset($data['telefono'])) $voluntario->setTelefono($data['telefono']);
        
        // Actualización de Preferencias (Borrar y re-crear es una estrategia válida simple)
        if (isset($data['preferencias_ids']) && is_array($data['preferencias_ids'])) {
            // Limpiamos anteriores
            foreach ($voluntario->getPreferencias() as $pref) {
                $voluntario->removePreferencia($pref);
            }
            // Añadimos nuevas
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

        // USAMOS EL SP, igual que en UsuarioController, para que la lógica de BBDD mande.
        // El SP se encarga de setDeletedAt(NOW) y setEstadoCuenta('Bloqueada')
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
}