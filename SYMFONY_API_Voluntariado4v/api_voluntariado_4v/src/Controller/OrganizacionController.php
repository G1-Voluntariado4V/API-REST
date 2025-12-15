<?php

namespace App\Controller;

use App\Entity\Organizacion;
use App\Entity\Usuario;
use App\Repository\ActividadRepository; // Necesario para listar actividades propias
use App\Repository\RolRepository;
use App\Repository\UsuarioRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('', name: 'api_')]
#[OA\Tag(name: 'Organizaciones', description: 'Gestión de ONGs y entidades')]
final class OrganizacionController extends AbstractController
{
    // ========================================================================
    // 1. LISTAR (GET) - Vista SQL
    // ========================================================================
    #[Route('/organizaciones', name: 'listar_organizaciones', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Listado de organizaciones activas (Vista SQL)',
        content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'object'))
    )]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        $conn = $em->getConnection();
        // Usamos la vista para filtrar automáticamente las borradas/bloqueadas
        $sql = 'SELECT * FROM VW_Organizaciones_Activas';
        try {
            $listado = $conn->executeQuery($sql)->fetchAllAssociative();
            return $this->json($listado, Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al cargar organizaciones'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ========================================================================
    // REGISTRAR ORGANIZACIÓN (POST)
    // ========================================================================
    #[Route('/organizaciones', name: 'registro_organizacion', methods: ['POST'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'google_id', type: 'string'),
                new OA\Property(property: 'correo', type: 'string'),
                new OA\Property(property: 'nombre', type: 'string', description: 'Nombre de la ONG'),
                new OA\Property(property: 'cif', type: 'string'),
                new OA\Property(property: 'descripcion', type: 'string'),
                new OA\Property(property: 'direccion', type: 'string'),
                new OA\Property(property: 'sitio_web', type: 'string'),
                new OA\Property(property: 'telefono', type: 'string')
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Organización registrada correctamente')]
    public function registrar(
        Request $request,
        EntityManagerInterface $em,
        RolRepository $rolRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Validar datos mínimos
        if (!isset($data['google_id'], $data['correo'], $data['cif'], $data['nombre'])) {
            return $this->json(['error' => 'Faltan datos obligatorios (CIF, Nombre, GoogleID...)'], 400);
        }

        $em->beginTransaction();
        try {
            // 1. Crear Usuario Base
            $usuario = new Usuario();
            $usuario->setCorreo($data['correo']);
            $usuario->setGoogleId($data['google_id']);
            $usuario->setEstadoCuenta('Pendiente'); // Las ONGs suelen requerir aprobación

            // Asignar Rol "Organizacion" (ID 3 según tus mocks)
            $rolOrg = $rolRepository->findOneBy(['nombreRol' => 'Organizacion']);
            if (!$rolOrg) throw new \Exception("Rol 'Organizacion' no encontrado en BBDD");

            $usuario->setRol($rolOrg);

            $em->persist($usuario);
            $em->flush(); // Para obtener ID

            // 2. Crear Perfil Organización
            $org = new Organizacion();
            $org->setUsuario($usuario); // PK = FK (1:1)
            $org->setCif($data['cif']);
            $org->setNombre($data['nombre']);
            $org->setDescripcion($data['descripcion'] ?? null);
            $org->setDireccion($data['direccion'] ?? null);
            $org->setSitioWeb($data['sitio_web'] ?? null);
            $org->setTelefono($data['telefono'] ?? null);

            $em->persist($org);
            $em->flush();
            $em->commit();

            return $this->json($org, Response::HTTP_CREATED, [], ['groups' => 'usuario:read']);
        } catch (UniqueConstraintViolationException $e) {
            $em->rollback();
            return $this->json(['error' => 'El correo o CIF ya están registrados'], 409);
        } catch (\Exception $e) {
            $em->rollback();
            return $this->json(['error' => 'Error al registrar: ' . $e->getMessage()], 500);
        }
    }

    // ========================================================================
    // 3. GET ONE (Ver Perfil)
    // ========================================================================
    #[Route('/organizaciones/{id}', name: 'get_organizacion', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Perfil de la organización')]
    #[OA\Response(response: 404, description: 'No encontrada')]
    public function getOne(int $id, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        $usuario = $userRepo->find($id);
        // Si no existe o tiene deleted_at (Soft Deleted)
        if (!$usuario || $usuario->getDeletedAt()) {
            return $this->json(['error' => 'Organización no encontrada'], Response::HTTP_NOT_FOUND);
        }

        // Buscamos el perfil específico
        $org = $em->getRepository(Organizacion::class)->findOneBy(['usuario' => $usuario]);
        if (!$org) return $this->json(['error' => 'Perfil incompleto'], Response::HTTP_NOT_FOUND);

        return $this->json($org, Response::HTTP_OK, [], ['groups' => 'usuario:read']);
    }

    // ========================================================================
    // 4. ACTUALIZAR (PUT) - Gestión de Datos Propios
    // ========================================================================
    #[Route('/organizaciones/{id}', name: 'actualizar_organizacion', methods: ['PUT'])]
    #[OA\RequestBody(description: 'Datos editables de la ONG', content: new OA\JsonContent(type: 'object'))]
    public function actualizar(int $id, Request $request, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        $usuario = $userRepo->find($id);
        if (!$usuario || $usuario->getDeletedAt()) return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);

        $org = $em->getRepository(Organizacion::class)->findOneBy(['usuario' => $usuario]);
        if (!$org) return $this->json(['error' => 'Perfil no encontrado'], Response::HTTP_NOT_FOUND);

        $data = json_decode($request->getContent(), true);

        // Actualización segura: No permitimos cambiar CIF ni Usuario ID por aquí
        if (isset($data['nombre'])) $org->setNombre($data['nombre']);
        if (isset($data['descripcion'])) $org->setDescripcion($data['descripcion']);
        if (isset($data['direccion'])) $org->setDireccion($data['direccion']);
        if (isset($data['sitio_web'])) $org->setSitioWeb($data['sitio_web']);
        if (isset($data['telefono'])) $org->setTelefono($data['telefono']);
        if (isset($data['img_perfil'])) $org->setImgPerfil($data['img_perfil']);

        $org->setUpdatedAt(new \DateTime());
        $em->flush();

        return $this->json($org, Response::HTTP_OK, [], ['groups' => 'usuario:read']);
    }

    // ========================================================================
    // 5. ELIMINAR (DELETE) - Baja de la Organización
    // ========================================================================
    #[Route('/organizaciones/{id}', name: 'borrar_organizacion', methods: ['DELETE'])]
    #[OA\Response(response: 200, description: 'Cuenta cerrada (Soft Delete)')]
    public function eliminar(int $id, UsuarioRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        $usuario = $userRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);

        // Usamos el SP para asegurar borrado lógico y bloqueo de cuenta
        $sql = 'EXEC SP_SoftDelete_Usuario @id_usuario = :id';
        try {
            $em->getConnection()->executeStatement($sql, ['id' => $id]);
            return $this->json(['mensaje' => 'Organización eliminada correctamente. Sus actividades siguen en histórico.'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al eliminar organización'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ========================================================================
    // 6. MIS ACTIVIDADES (GET) - Listado propio
    // ========================================================================
    #[Route('/organizaciones/{id}/actividades', name: 'mis_actividades_org', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Lista de actividades creadas por esta ONG')]
    public function misActividades(
        int $id,
        ActividadRepository $actRepo,
        UsuarioRepository $userRepo
    ): JsonResponse {
        // 1. Primero verificamos que la Organización existe
        // Recordamos que Organizacion hereda de Usuario, así que buscamos por ese ID
        $organizacion = $userRepo->find($id);

        if (!$organizacion || !in_array('Organizacion', $organizacion->getRoles() ?? [])) {
            // Nota: Ajusta la lógica de roles según cómo los guardes en BBDD (si usas string o array)
            // Si tu BBDD usa roles relacionales, verifica la entidad Organizacion directamente:
            // $organizacion = $entityManager->getRepository(Organizacion::class)->find($id);
        }

        // Forma más directa si tienes repositorio de Organizacion inyectado:
        // $organizacionEntity = $orgRepo->find($id); 

        // 2. Buscamos actividades pasando el OBJETO organización, no el int.
        // Doctrine prefiere objetos para las relaciones.
        $actividades = $actRepo->findBy(['organizacion' => $id]);

        // Mapeamos a un formato ligero para la lista
        $resultado = [];
        foreach ($actividades as $act) {
            // Solo mostramos si NO está borrada (Soft Delete)
            // Asumimos que implementaste getDeletedAt() en la entidad Actividad como hablamos
            if (method_exists($act, 'getDeletedAt') && !$act->getDeletedAt()) {
                $resultado[] = [
                    'id_actividad' => $act->getId(),
                    'titulo' => $act->getTitulo(),
                    'fecha_inicio' => $act->getFechaInicio()->format('Y-m-d H:i:s'),
                    'estado_publicacion' => $act->getEstadoPublicacion(),
                    'cupo_maximo' => $act->getCupoMaximo(),
                    // Opcional: contar inscripciones aceptadas
                    'inscritos' => $act->getInscripciones()->filter(function ($insc) {
                        return $insc->getEstadoSolicitud() === 'Aceptada';
                    })->count()
                ];
            }
        }

        return $this->json($resultado, Response::HTTP_OK);
    }
}
