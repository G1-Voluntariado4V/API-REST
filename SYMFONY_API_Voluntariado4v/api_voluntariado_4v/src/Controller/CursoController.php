<?php

namespace App\Controller;

use App\Repository\CursoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
final class CursoController extends AbstractController
{
    #[Route('/cursos', name: 'cursos_index', methods: ['GET'])]
    public function index(CursoRepository $cursoRepository): JsonResponse
    {
        // 1. Pedimos todos los cursos al repositorio
        $cursos = $cursoRepository->findAll();

        // 2. Devolvemos la respuesta en JSON directamente
        // Symfony se encarga de convertir el array de objetos a JSON
        return $this->json($cursos, 200, [], ['groups' => 'curso:read']);
    }
}
