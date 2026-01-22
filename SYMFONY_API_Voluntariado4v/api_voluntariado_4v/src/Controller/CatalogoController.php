<?php

namespace App\Controller;

use App\Repository\CursoRepository;
use App\Repository\IdiomaRepository;
use App\Repository\TipoVoluntariadoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
}
