<?php

namespace App\Controller;

use App\Repository\CursoRepository;
use App\Repository\IdiomaRepository;
use App\Repository\TipoVoluntariadoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('/catalogos', name: 'api_catalogos_')]
final class CatalogoController extends AbstractController
{
    // ========================================================================
    // 1. CURSOS (GET)
    // ========================================================================
    #[Route('/cursos', name: 'cursos', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Lista de cursos',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'nombre', type: 'string'),
                new OA\Property(property: 'grado', type: 'string', example: 'Superior'),
                new OA\Property(property: 'nivel', type: 'integer', example: 1)
            ])
        )
    )]
    public function cursos(CursoRepository $repo): JsonResponse
    {
        return $this->json($repo->findAll(), 200, [
            'Cache-Control' => 'public, max-age=3600'
        ]);
    }

    // ========================================================================
    // 2. IDIOMAS (GET)
    // ========================================================================
    #[Route('/idiomas', name: 'idiomas', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Lista de idiomas disponibles',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'nombre', type: 'string'),
                new OA\Property(property: 'codigo_iso', type: 'string', example: 'ES')
            ])
        )
    )]
    public function idiomas(IdiomaRepository $repo): JsonResponse
    {
        return $this->json($repo->findAll(), 200, ['Cache-Control' => 'public, max-age=3600']);
    }

    // ========================================================================
    // 3. TIPOS DE VOLUNTARIADO (GET)
    // ========================================================================
    #[Route('/tipos-voluntariado', name: 'tipos', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Lista de tipos de voluntariado',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(properties: [
                new OA\Property(property: 'id', type: 'integer'),
                new OA\Property(property: 'nombreTipo', type: 'string')
            ])
        )
    )]
    public function tipos(TipoVoluntariadoRepository $repo): JsonResponse
    {
        return $this->json($repo->findAll(), 200, ['Cache-Control' => 'public, max-age=3600']);
    }

    // ========================================================================
    // 4. CREAR TIPO DE VOLUNTARIADO (POST)
    // ========================================================================
    #[Route('/tipos-voluntariado', name: 'crear_tipo', methods: ['POST'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'nombreTipo', type: 'string', example: 'Presencial')
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Tipo creado correctamente')]
    #[OA\Response(response: 400, description: 'Datos inválidos')]
    public function createTipo(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['nombreTipo'])) {
            return $this->json(['error' => 'Falta el nombreTipo'], Response::HTTP_BAD_REQUEST);
        }

        $tipo = new \App\Entity\TipoVoluntariado();
        $tipo->setNombreTipo($data['nombreTipo']);

        $em->persist($tipo);
        $em->flush();

        return $this->json($tipo, Response::HTTP_CREATED);
    }

    // ========================================================================
    // 5. ACTUALIZAR TIPO DE VOLUNTARIADO (PUT)
    // ========================================================================
    #[Route('/tipos-voluntariado/{id}', name: 'update_tipo', methods: ['PUT'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'nombreTipo', type: 'string')
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Tipo actualizado')]
    #[OA\Response(response: 404, description: 'Tipo no encontrado')]
    public function updateTipo(int $id, Request $request, TipoVoluntariadoRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $tipo = $repo->find($id);
        if (!$tipo) {
            return $this->json(['error' => 'Tipo de voluntariado no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['nombreTipo'])) {
            $tipo->setNombreTipo($data['nombreTipo']);
        }

        $em->flush();

        return $this->json($tipo, Response::HTTP_OK);
    }

    // ========================================================================
    // 6. ELIMINAR TIPO DE VOLUNTARIADO (DELETE)
    // ========================================================================
    #[Route('/tipos-voluntariado/{id}', name: 'delete_tipo', methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Tipo eliminado')]
    #[OA\Response(response: 404, description: 'Tipo no encontrado')]
    public function deleteTipo(int $id, TipoVoluntariadoRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $tipo = $repo->find($id);
        if (!$tipo) {
            return $this->json(['error' => 'Tipo de voluntariado no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($tipo);
        $em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
