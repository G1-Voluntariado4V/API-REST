<?php

namespace App\Controller;

// 1. IMPORTANTE: Aquí añadimos las herramientas que faltaban
use App\Entity\Rol;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request; // <--- Necesario para leer el JSON
use Symfony\Component\Routing\Attribute\Route;

// He añadido la ruta global /api para que sea más profesional
#[Route('/api', name: 'api_')]
final class RolController extends AbstractController
{
    // Cambiamos 'index' por 'crear' y definimos que sea POST
    #[Route('/rol', name: 'crear_rol', methods: ['POST'])]
    public function crear(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        // A. Leer el JSON que nos envía Postman
        $data = json_decode($request->getContent(), true);

        // Validación simple
        if (!isset($data['nombre'])) {
            return $this->json(['error' => 'Falta el nombre del rol'], 400);
        }

        // B. Crear el objeto ROL
        $rol = new Rol();
        $rol->setNombre($data['nombre']);

        // C. Guardar en SQL Server
        try {
            $entityManager->persist($rol); // Preparar
            $entityManager->flush();       // Ejecutar INSERT
        } catch (\Exception $e) {
            return $this->json(['error' => 'No se pudo guardar: ' . $e->getMessage()], 500);
        }

        // D. Responder
        return $this->json([
            'mensaje' => 'Rol guardado con éxito',
            'id' => $rol->getId(),
            'nombre' => $rol->getNombre()
        ], 201);
    }
}
