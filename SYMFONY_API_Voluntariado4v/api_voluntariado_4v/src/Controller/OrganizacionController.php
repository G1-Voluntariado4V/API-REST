<?php

namespace App\Controller;


use App\Entity\Organizacion;
use App\Entity\Usuario;
use App\Model\Organizacion\OrganizacionResponseDTO;
use App\Model\Organizacion\OrganizacionUpdateDTO;
use App\Repository\OrganizacionRepository;
use App\Repository\UsuarioRepository;
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
#[OA\Tag(name: 'Organizaciones', description: 'Gestión de perfiles de ONGs')]
final class OrganizacionController extends AbstractController
{
    // ========================================================================
    // 1. LISTAR ORGANIZACIONES (GET) - VISTA SQL
    // ========================================================================
    #[Route('/organizaciones', name: 'listar_organizaciones', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Catálogo de ONGs activas',
        content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'object'))
    )]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        // Usamos la Vista SQL para máximo rendimiento y consistencia con VoluntarioController
        $conn = $em->getConnection();
        $sql = 'SELECT * FROM VW_Organizaciones_Activas';

        try {
            $organizaciones = $conn->executeQuery($sql)->fetchAllAssociative();
            return $this->json($organizaciones, Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al obtener organizaciones: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    // ========================================================================
    // 2. DETALLE ORGANIZACION (GET) - CON DTO
    // ========================================================================
    #[Route('/organizaciones/{id}', name: 'detalle_organizacion', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Detalle público de la Organización',
        content: new OA\JsonContent(
            ref: new Model(type: OrganizacionResponseDTO::class)
        )
    )]
    public function show(int $id, UsuarioRepository $userRepo, OrganizacionRepository $orgRepo): JsonResponse
    {
        // 1. Buscar Usuario base (para verificar soft delete)
        $usuario = $userRepo->find($id);
        if (!$usuario || $usuario->getDeletedAt()) {
            return $this->json(['error' => 'Organización no encontrada o inactiva'], Response::HTTP_NOT_FOUND);
        }

        // 2. Buscar Perfil Organización
        $organizacion = $orgRepo->findOneBy(['usuario' => $usuario]);
        if (!$organizacion) {
            return $this->json(['error' => 'Perfil de organización no configurado'], Response::HTTP_NOT_FOUND);
        }

        // 3. Devolver DTO
        return $this->json(OrganizacionResponseDTO::fromEntity($organizacion), Response::HTTP_OK);
    }

    // ========================================================================
    // 3. ACTUALIZAR PERFIL (PUT) - CON VALIDACIÓN DTO
    // ========================================================================
    #[Route('/organizaciones/{id}', name: 'update_organizacion', methods: ['PUT'])]
    #[OA\RequestBody(
        description: 'Datos a actualizar',
        required: true,
        content: new OA\JsonContent(
            ref: new Model(type: OrganizacionUpdateDTO::class)
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Organización actualizada',
        content: new OA\JsonContent(
            ref: new Model(type: OrganizacionResponseDTO::class)
        )
    )]
    public function update(
        int $id,
        #[MapRequestPayload] OrganizacionUpdateDTO $dto, // Valida automáticamente
        UsuarioRepository $userRepo,
        OrganizacionRepository $orgRepo,
        EntityManagerInterface $em
    ): JsonResponse {
        $usuario = $userRepo->find($id);
        if (!$usuario || $usuario->getDeletedAt()) {
            return $this->json(['error' => 'Usuario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $organizacion = $orgRepo->findOneBy(['usuario' => $usuario]);
        if (!$organizacion) {
            return $this->json(['error' => 'Perfil no encontrado'], Response::HTTP_NOT_FOUND);
        }

        // Actualizamos campos permitidos
        $organizacion->setNombre($dto->nombre);
        $organizacion->setDescripcion($dto->descripcion);
        $organizacion->setSitioWeb($dto->sitioWeb);
        $organizacion->setDireccion($dto->direccion);
        $organizacion->setTelefono($dto->telefono);

        try {
            $em->flush(); // El trigger actualizará updated_at

            return $this->json(
                OrganizacionResponseDTO::fromEntity($organizacion),
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al actualizar: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
