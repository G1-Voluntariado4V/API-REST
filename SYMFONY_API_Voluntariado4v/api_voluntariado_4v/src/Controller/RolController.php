<?php

namespace App\Controller;

use App\Entity\Rol;
use App\Repository\RolRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/roles', name: 'api_roles_')]
final class RolController extends AbstractController
{
    // GET: Listar todos los roles
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(RolRepository $rolRepo): JsonResponse
    {
        return $this->json($rolRepo->findAll());
    }

    // POST: Crear nuevo rol
    #[Route('', name: 'crear', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['nombre'])) {
            return $this->json(['error' => 'Falta el nombre del rol'], 400);
        }

        $rol = new Rol();
        $rol->setNombre($data['nombre']);

        $em->persist($rol);
        $em->flush();

        return $this->json($rol, 201);
    }
}
