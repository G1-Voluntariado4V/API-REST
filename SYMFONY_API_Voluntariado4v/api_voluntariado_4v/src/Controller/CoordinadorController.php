<?php

namespace App\Controller;

use App\Entity\Actividad;
use App\Entity\Coordinador;
use App\Entity\Usuario;
use App\Repository\ActividadRepository;
use App\Repository\RolRepository;
use App\Repository\UsuarioRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('', name: 'api_')]
#[OA\Tag(name: 'Coordinadores', description: 'Gestión interna, Dashboard y Moderación Global')]
final class CoordinadorController extends AbstractController
{
    // ========================================================================
    // 1. DASHBOARD GLOBAL (Estadísticas vía SP)
    // ========================================================================
    #[Route('/coord/stats', name: 'coord_stats', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Métricas globales del sistema (Usando SP SQL)',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'titulo', type: 'string'),
                new OA\Property(property: 'metricas', type: 'object', example: ['voluntarios_activos' => 10, 'actividades_pendientes' => 2])
            ]
        )
    )]
    public function dashboard(EntityManagerInterface $em): JsonResponse
    {
        $conn = $em->getConnection();
        // Llamada al Procedimiento Almacenado optimizado
        $sql = 'EXEC SP_Dashboard_Stats';

        try {
            $stats = $conn->executeQuery($sql)->fetchAssociative();
            return $this->json([
                'titulo' => 'Panel de Control General',
                'fecha_generacion' => date('Y-m-d H:i:s'),
                'metricas' => $stats
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error calculando estadísticas: ' . $e->getMessage()], 
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    // ========================================================================
    // 2. REGISTRAR COORDINADOR (POST)
    // ========================================================================
    #[Route('/coordinadores', name: 'registro_coordinador', methods: ['POST'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'nombre', type: 'string'),
                new OA\Property(property: 'correo', type: 'string'),
                new OA\Property(property: 'google_id', type: 'string')
            ]
        )
    )]
    public function registrar(
        Request $request,
        EntityManagerInterface $entityManager,
        RolRepository $rolRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['google_id'], $data['correo'], $data['nombre'])) {
            return $this->json(['error' => 'Faltan datos obligatorios'], Response::HTTP_BAD_REQUEST);
        }

        $entityManager->beginTransaction();
        try {
            // A. USUARIO BASE
            $usuario = new Usuario();
            $usuario->setCorreo($data['correo']);
            $usuario->setGoogleId($data['google_id']);
            $usuario->setEstadoCuenta('Activa'); // Los jefes nacen activos

            $rolCoord = $rolRepository->findOneBy(['nombre' => 'Coordinador']);
            if (!$rolCoord) throw new \Exception("Error config: Rol 'Coordinador' no existe.");
            $usuario->setRol($rolCoord);

            $entityManager->persist($usuario);
            $entityManager->flush();

            // B. PERFIL COORDINADOR
            $coord = new Coordinador();
            $coord->setUsuario($usuario);
            $coord->setNombre($data['nombre']);
            $coord->setApellidos($data['apellidos'] ?? null);
            $coord->setTelefono($data['telefono'] ?? null);
            $coord->setUpdatedAt(new \DateTime());

            $entityManager->persist($coord);
            $entityManager->flush();
            
            $entityManager->commit();

            return $this->json([
                'mensaje' => 'Coordinador registrado correctamente',
                'perfil' => [
                    'id_usuario' => $usuario->getId(),
                    'nombre' => $coord->getNombre(),
                    'rol' => 'Coordinador'
                ]
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            $entityManager->rollback();
            return $this->json(['error' => 'Error al registrar: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ========================================================================
    // 3. VER MI PERFIL
    // ========================================================================
    #[Route('/coordinadores/{id}', name: 'get_coordinador', methods: ['GET'])]
    public function getOne(int $id, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        $usuario = $userRepo->find($id);

        if (!$usuario || $usuario->getDeletedAt()) {
            return $this->json(['error' => 'Coordinador no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $coord = $em->getRepository(Coordinador::class)->findOneBy(['usuario' => $usuario]);
        if (!$coord) {
            return $this->json(['error' => 'Perfil de datos incompleto'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($coord, Response::HTTP_OK, [], ['groups' => 'usuario:read']);
    }

    // ========================================================================
    // 4. ACTUALIZAR PERFIL
    // ========================================================================
    #[Route('/coordinadores/{id}', name: 'actualizar_coordinador', methods: ['PUT'])]
    #[OA\RequestBody(description: 'Datos a actualizar', content: new OA\JsonContent(type: 'object'))]
    public function actualizar(
        int $id,
        Request $request,
        UsuarioRepository $userRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $usuario = $userRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);

        $coord = $em->getRepository(Coordinador::class)->findOneBy(['usuario' => $usuario]);
        if (!$coord) return $this->json(['error' => 'Perfil no encontrado'], Response::HTTP_NOT_FOUND);

        $data = json_decode($request->getContent(), true);

        if (isset($data['nombre'])) $coord->setNombre($data['nombre']);
        if (isset($data['apellidos'])) $coord->setApellidos($data['apellidos']);
        if (isset($data['telefono'])) $coord->setTelefono($data['telefono']);
        
        $coord->setUpdatedAt(new \DateTime());
        $em->flush();

        return $this->json($coord, Response::HTTP_OK, [], ['groups' => 'usuario:read']);
    }

    // ========================================================================
    // 5. ELIMINAR CUENTA (USANDO SP)
    // ========================================================================
    #[Route('/coordinadores/{id}', name: 'borrar_coordinador', methods: ['DELETE'])]
    public function eliminar(
        int $id,
        UsuarioRepository $userRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $usuario = $userRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);

        // Usamos SP para consistencia
        $sql = 'EXEC SP_SoftDelete_Usuario @id_usuario = :id';
        try {
            $em->getConnection()->executeStatement($sql, ['id' => $id]);
            return $this->json(['mensaje' => 'Cuenta cerrada correctamente'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al cerrar cuenta'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    // ========================================================================
    // 6. MODERACIÓN: GESTIÓN DE ESTADO DE USUARIOS (Voluntarios/Organizaciones)
    // ========================================================================
    #[Route('/coord/{rol}/{id}/estado', name: 'coord_cambiar_estado_usuario', methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'Estado de usuario actualizado.')]
    #[OA\Response(response: 400, description: 'Estado o Rol inválido.')]
    #[OA\Parameter(name: 'rol', description: 'Tipo de usuario (voluntarios o organizaciones)', in: 'path', schema: new OA\Schema(type: 'string', enum: ['voluntarios', 'organizaciones']))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'estado', type: 'string', description: 'Nuevo estado', enum: ['Activa', 'Rechazada', 'Bloqueada'])
            ]
        )
    )]
    public function cambiarEstadoUsuario(
        int $id,
        string $rol,
        Request $request,
        UsuarioRepository $userRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $nuevoEstado = $data['estado'] ?? null;
        $estadosValidos = ['Activa', 'Rechazada', 'Bloqueada'];
    
        // 1. Validar Rol y Estado
        if (!in_array($rol, ['voluntarios', 'organizaciones'])) {
            return $this->json(['error' => 'Rol de usuario inválido para esta acción.'], Response::HTTP_BAD_REQUEST);
        }
        if (!in_array($nuevoEstado, $estadosValidos)) {
            return $this->json(['error' => 'Estado de cuenta inválido. Use: ' . implode(', ', $estadosValidos)], Response::HTTP_BAD_REQUEST);
        }
    
        // 2. Buscar y Actualizar
        $usuario = $userRepo->find($id);
        if (!$usuario) {
            return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        }
    
        $usuario->setEstadoCuenta($nuevoEstado);
        $usuario->setUpdatedAt(new \DateTime());
        $em->flush();
    
        return $this->json(['mensaje' => 'Estado de la cuenta actualizado a ' . $nuevoEstado], Response::HTTP_OK);
    }
    
    // ========================================================================
    // 7. GESTIÓN TOTAL DE ACTIVIDADES (COORDINACIÓN)
    // ========================================================================
    
    // 7.1 MODERAR ESTADO (Publicar/Rechazar)
    #[Route('/coord/actividades/{id}/estado', name: 'coord_cambiar_estado_actividad', methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'Estado de publicación actualizado.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'estado', type: 'string', enum: ['Publicada', 'En revision', 'Cancelada'])]))]
    public function cambiarEstadoActividad(int $id, Request $request, EntityManagerInterface $em): JsonResponse 
    {
        $data = json_decode($request->getContent(), true);
        $nuevoEstado = $data['estado'] ?? null;
        $estadosValidos = ['Publicada', 'En revision', 'Cancelada'];
        
        if (!in_array($nuevoEstado, $estadosValidos)) {
            return $this->json(['error' => 'Estado de publicación inválido. Use: ' . implode(', ', $estadosValidos)], Response::HTTP_BAD_REQUEST);
        }
    
        $actividad = $em->getRepository(Actividad::class)->find($id);
        if (!$actividad) return $this->json(['error' => 'Actividad no encontrada'], Response::HTTP_NOT_FOUND);
    
        $actividad->setEstadoPublicacion($nuevoEstado);
        $actividad->setUpdatedAt(new \DateTime());
        $em->flush();
    
        return $this->json(['mensaje' => 'Estado de publicación actualizado a ' . $nuevoEstado], Response::HTTP_OK);
    }

    // 7.2 BORRAR ACTIVIDAD (COORD DELETE) - Borrado forzoso
    #[Route('/coord/actividades/{id}', name: 'coord_borrar_actividad', methods: ['DELETE'])]
    #[OA\Response(response: 200, description: 'Actividad eliminada por coordinación (Soft Delete)')]
    public function borrarActividadCoord(int $id, EntityManagerInterface $em): JsonResponse
    {
        // Reutilizamos el SP de Soft Delete para consistencia
        $sql = 'EXEC SP_SoftDelete_Actividad @id_actividad = :id';
        try {
            $em->getConnection()->executeStatement($sql, ['id' => $id]);
            return $this->json(['mensaje' => 'Actividad eliminada por Coordinador'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al eliminar actividad'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // 7.3 MODIFICAR ACTIVIDAD (COORD UPDATE) - Edición de contenido
    #[Route('/coord/actividades/{id}', name: 'coord_editar_actividad', methods: ['PUT'])]
    #[OA\RequestBody(description: 'Datos a forzar actualización', content: new OA\JsonContent(type: 'object'))]
    public function editarActividadCoord(int $id, Request $request, ActividadRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        // Esta función permite al coordinador corregir textos o datos de cualquier actividad
        $actividad = $repo->find($id);
        if (!$actividad) return $this->json(['error' => 'Actividad no encontrada'], Response::HTTP_NOT_FOUND);

        $data = json_decode($request->getContent(), true);
        
        // Actualización simple de campos escalares (Título, Descripción, etc.)
        if (isset($data['titulo'])) $actividad->setTitulo($data['titulo']);
        if (isset($data['descripcion'])) $actividad->setDescripcion($data['descripcion']);
        if (isset($data['cupo_maximo'])) $actividad->setCupoMaximo($data['cupo_maximo']);
        // ... (añadir resto de campos si necesario, ej. ubicación, fechas)

        $actividad->setUpdatedAt(new \DateTime());
        $em->flush();

        return $this->json(['mensaje' => 'Actividad editada por Coordinación', 'actividad' => $actividad->getTitulo()], Response::HTTP_OK);
    }
}