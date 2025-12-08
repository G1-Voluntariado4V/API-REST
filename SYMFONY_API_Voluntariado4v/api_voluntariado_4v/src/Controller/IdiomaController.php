<?php

namespace App\Controller;

use App\Repository\IdiomaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
final class IdiomaController extends AbstractController
{
    #[Route('/idiomas', name: 'idiomas_index', methods: ['GET'])]
    public function index(IdiomaRepository $idiomaRepository): JsonResponse
    {
        $idiomas = $idiomaRepository->findAll();
        return $this->json($idiomas, 200, [], ['groups' => 'idioma:read']);
    }
}
