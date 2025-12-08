<?php

namespace App\Controller;

use App\Entity\VoluntarioIdioma;
use App\Repository\VoluntarioRepository;
use App\Repository\IdiomaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_voluntario_idiomas_')]
final class VoluntarioIdiomaController extends AbstractController
{
    // ========================================================================
    // 0. ASIGNAR UN IDIOMA NUEVO (POST)
    // URL: /api/voluntarios/{idVoluntario}/idiomas
    // Body: { "id_idioma": 2, "nivel": "B2" }
    // ========================================================================
    #[Route('/voluntarios/{idVoluntario}/idiomas', name: 'create', methods: ['POST'])]
    public function create(
        int $idVoluntario,
        Request $request,
        EntityManagerInterface $entityManager,
        VoluntarioRepository $voluntarioRepo,
        IdiomaRepository $idiomaRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['id_idioma'], $data['nivel'])) {
            return $this->json(['error' => 'Faltan datos (id_idioma, nivel)'], 400);
        }

        // 1. Buscar Voluntario (usando ID usuario)
        // Ojo: Buscamos en VoluntarioRepository para asegurarnos que es un voluntario y no una ONG
        $voluntario = $voluntarioRepo->findOneBy(['usuario' => $idVoluntario]);
        if (!$voluntario) {
            return $this->json(['error' => 'Voluntario no encontrado'], 404);
        }

        // 2. Buscar Idioma
        $idioma = $idiomaRepo->find($data['id_idioma']);
        if (!$idioma) {
            return $this->json(['error' => 'Idioma no encontrado'], 404);
        }

        // 3. Verificar si YA lo tiene (para no duplicar y que explote la PK)
        $existe = $entityManager->getRepository(VoluntarioIdioma::class)->findOneBy([
            'voluntario' => $idVoluntario,
            'idioma' => $data['id_idioma']
        ]);

        if ($existe) {
            return $this->json(['error' => 'El voluntario ya tiene asignado este idioma. Usa PUT para modificarlo.'], 409);
        }

        // 4. Crear la relación
        $voluntarioIdioma = new VoluntarioIdioma();
        $voluntarioIdioma->setVoluntario($voluntario);
        $voluntarioIdioma->setIdioma($idioma);
        $voluntarioIdioma->setNivel($data['nivel']);

        $entityManager->persist($voluntarioIdioma);
        $entityManager->flush();

        return $this->json(['mensaje' => 'Idioma asignado correctamente'], 201);
    }


    // ========================================================================
    // 1. MODIFICAR EL NIVEL DE UN IDIOMA (PUT)
    // URL Ejemplo: /api/voluntarios/2/idiomas/1
    // (Significa: Del voluntario 2, edita el idioma 1)
    // ========================================================================
    #[Route('/voluntarios/{idVoluntario}/idiomas/{idIdioma}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(
        int $idVoluntario,
        int $idIdioma,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        // 1. Buscamos la relación exacta usando la CLAVE COMPUESTA
        // Doctrine busca una fila donde la columna 'voluntario' sea X y la columna 'idioma' sea Y
        $relacion = $entityManager->getRepository(VoluntarioIdioma::class)->findOneBy([
            'voluntario' => $idVoluntario,
            'idioma' => $idIdioma
        ]);

        if (!$relacion) {
            return $this->json(['error' => 'Este voluntario no tiene asignado ese idioma'], 404);
        }

        // 2. Decodificar JSON
        $data = json_decode($request->getContent(), true);

        // 3. Actualizar el nivel si viene en el body
        if (isset($data['nivel'])) {
            $relacion->setNivel($data['nivel']);

            $entityManager->flush();

            return $this->json([
                'mensaje' => 'Nivel de idioma actualizado correctamente',
                'nuevo_nivel' => $data['nivel']
            ], 200);
        }

        return $this->json(['error' => 'No se envió el campo "nivel"'], 400);
    }

    // ========================================================================
    // 2. BORRAR UN IDIOMA DEL PERFIL (DELETE)
    // URL Ejemplo: /api/voluntarios/2/idiomas/1
    // ========================================================================
    #[Route('/voluntarios/{idVoluntario}/idiomas/{idIdioma}', name: 'delete', methods: ['DELETE'])]
    public function delete(
        int $idVoluntario,
        int $idIdioma,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        // 1. Buscamos la relación exacta
        $relacion = $entityManager->getRepository(VoluntarioIdioma::class)->findOneBy([
            'voluntario' => $idVoluntario,
            'idioma' => $idIdioma
        ]);

        if (!$relacion) {
            return $this->json(['error' => 'El voluntario no tiene este idioma asignado'], 404);
        }

        // 2. Borramos la relación
        $entityManager->remove($relacion);
        $entityManager->flush();

        return $this->json(['mensaje' => 'Idioma eliminado del perfil del voluntario'], 200);
    }
}
