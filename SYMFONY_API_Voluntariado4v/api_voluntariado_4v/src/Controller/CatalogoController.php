<?php
//Cursos, idiomas, ods y tipos de voluntariado
namespace App\Controller;

use App\Repository\CursoRepository;
use App\Repository\IdiomaRepository;
use App\Repository\ODSRepository;
use App\Repository\TipoVoluntariadoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

// Agrupamos todo bajo /api/catalogos para que el Frontend lo tenga ordenado
#[Route('/api/catalogos', name: 'api_catalogos_')]
final class CatalogoController extends AbstractController
{
    // 1. CURSOS
    #[Route('/cursos', name: 'cursos', methods: ['GET'])]
    public function cursos(CursoRepository $repo): JsonResponse
    {
        // Cache público: Estos datos cambian poco, decimos al navegador que los guarde 1 hora
        return $this->json($repo->findAll(), 200, [
            'Cache-Control' => 'public, max-age=3600'
        ]);
        // Nota: Asegúrate de que la entidad Curso no tenga 'groups' restrictivos o úsalos aquí
    }

    // 2. IDIOMAS
    #[Route('/idiomas', name: 'idiomas', methods: ['GET'])]
    public function idiomas(IdiomaRepository $repo): JsonResponse
    {
        return $this->json($repo->findAll(), 200, ['Cache-Control' => 'public, max-age=3600']);
    }

    // 3. ODS
    #[Route('/ods', name: 'ods', methods: ['GET'])]
    public function ods(ODSRepository $repo): JsonResponse
    {
        return $this->json($repo->findAll(), 200, ['Cache-Control' => 'public, max-age=3600']);
    }

    // 4. TIPOS DE VOLUNTARIADO
    #[Route('/tipos-voluntariado', name: 'tipos', methods: ['GET'])]
    public function tipos(TipoVoluntariadoRepository $repo): JsonResponse
    {
        return $this->json($repo->findAll(), 200, ['Cache-Control' => 'public, max-age=3600']);
    }
}
