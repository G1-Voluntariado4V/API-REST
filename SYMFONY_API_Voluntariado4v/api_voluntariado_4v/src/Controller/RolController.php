<?php

namespace App\Controller;

use App\Entity\Rol;
use App\Repository\RolRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response; // <--- Importante
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA; // <--- Importante para Swagger

#[Route('/roles', name: 'api_roles_')]
#[OA\Tag(name: 'Roles', description: 'Catálogo de roles del sistema')]
final class RolController extends AbstractController
{
    // GET: Listar todos los roles
    #[Route('', name: 'index', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Lista de roles disponibles',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'nombreRol', type: 'string') // Coincide con tu entidad
            ])
        )
    )]
    public function index(RolRepository $rolRepo): JsonResponse
    {
        return $this->json($rolRepo->findAll(), Response::HTTP_OK);
    }

    // POST: Crear nuevo rol
    #[Route('', name: 'crear', methods: ['POST'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'nombre', type: 'string', example: 'Coordinador')
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Rol creado')]
    #[OA\Response(response: 400, description: 'Datos inválidos')]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validación simple
        if (empty($data['nombre'])) {
            return $this->json(
                ['error' => 'Falta el nombre del rol'],
                Response::HTTP_BAD_REQUEST // 400
            );
        }

        $rol = new Rol();
        // Asumiendo que tu entidad mapea 'nombre_rol' a 'nombreRol' o 'nombre'
        // Asegúrate de usar el setter correcto.
        $rol->setNombre($data['nombre']);

        try {
            $em->persist($rol);
            $em->flush();
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al guardar en la base de datos'],
                Response::HTTP_INTERNAL_SERVER_ERROR // 500
            );
        }

        return $this->json($rol, Response::HTTP_CREATED); // 201
    }
}
