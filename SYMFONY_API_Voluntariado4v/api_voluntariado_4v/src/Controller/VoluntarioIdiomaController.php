<?php

namespace App\Controller;

use App\Entity\VoluntarioIdioma;
use App\Repository\VoluntarioRepository;
use App\Repository\IdiomaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('', name: 'api_voluntario_idiomas_')]
#[OA\Tag(name: 'Idiomas Voluntario', description: 'Gestión de habilidades lingüísticas del voluntario')]
final class VoluntarioIdiomaController extends AbstractController
{
    // ========================================================================
    // 1. ASIGNAR UN IDIOMA NUEVO (POST)
    // ========================================================================
    #[Route('/voluntarios/{idVoluntario}/idiomas', name: 'create', methods: ['POST'])]
    #[OA\Parameter(name: 'idVoluntario', in: 'path', description: 'ID del Usuario Voluntario')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id_idioma', type: 'integer', example: 1),
                new OA\Property(property: 'nivel', type: 'string', enum: ['A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'Nativo'], example: 'B2')
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Idioma añadido al perfil')]
    #[OA\Response(response: 409, description: 'El idioma ya está asignado')]
    public function create(
        int $idVoluntario,
        Request $request,
        EntityManagerInterface $entityManager,
        VoluntarioRepository $voluntarioRepo,
        IdiomaRepository $idiomaRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['id_idioma'], $data['nivel'])) {
            return $this->json(['error' => 'Faltan datos (id_idioma, nivel)'], Response::HTTP_BAD_REQUEST);
        }

        // 1. Buscar Voluntario (por usuario_id)
        $voluntario = $voluntarioRepo->findOneBy(['usuario' => $idVoluntario]);
        if (!$voluntario) {
            return $this->json(['error' => 'Voluntario no encontrado'], Response::HTTP_NOT_FOUND);
        }

        // 2. Buscar Idioma
        $idioma = $idiomaRepo->find($data['id_idioma']);
        if (!$idioma) {
            return $this->json(['error' => 'Idioma no encontrado'], Response::HTTP_NOT_FOUND);
        }

        // 3. Verificar duplicados
        $existe = $entityManager->getRepository(VoluntarioIdioma::class)->findOneBy([
            'voluntario' => $voluntario, // Usamos la entidad, no el ID
            'idioma' => $idioma
        ]);

        if ($existe) {
            return $this->json(['error' => 'El voluntario ya tiene asignado este idioma. Usa PUT para modificar el nivel.'], Response::HTTP_CONFLICT);
        }

        // 4. Crear relación
        $voluntarioIdioma = new VoluntarioIdioma();
        $voluntarioIdioma->setVoluntario($voluntario);
        $voluntarioIdioma->setIdioma($idioma);
        $voluntarioIdioma->setNivel($data['nivel']);

        $entityManager->persist($voluntarioIdioma);
        $entityManager->flush();

        return $this->json(['mensaje' => 'Idioma asignado correctamente'], Response::HTTP_CREATED);
    }

    // ========================================================================
    // 2. MODIFICAR EL NIVEL DE UN IDIOMA (PUT)
    // ========================================================================
    #[Route('/voluntarios/{idVoluntario}/idiomas/{idIdioma}', name: 'update', methods: ['PUT', 'PATCH'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'nivel', type: 'string', example: 'C1')
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Nivel actualizado')]
    public function update(
        int $idVoluntario,
        int $idIdioma,
        Request $request,
        EntityManagerInterface $entityManager,
        VoluntarioRepository $voluntarioRepo, // Necesitamos buscar primero al voluntario
        IdiomaRepository $idiomaRepo
    ): JsonResponse {
        // Buscar entidades para asegurar que existen
        $voluntario = $voluntarioRepo->findOneBy(['usuario' => $idVoluntario]);
        $idioma = $idiomaRepo->find($idIdioma);

        if (!$voluntario || !$idioma) {
            return $this->json(['error' => 'Voluntario o Idioma no encontrados'], Response::HTTP_NOT_FOUND);
        }

        // Buscar la relación exacta
        $relacion = $entityManager->getRepository(VoluntarioIdioma::class)->findOneBy([
            'voluntario' => $voluntario,
            'idioma' => $idioma
        ]);

        if (!$relacion) {
            return $this->json(['error' => 'Este voluntario no tiene asignado ese idioma'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['nivel'])) {
            $relacion->setNivel($data['nivel']);
            $entityManager->flush();
            return $this->json(['mensaje' => 'Nivel actualizado', 'nuevo_nivel' => $data['nivel']], Response::HTTP_OK);
        }

        return $this->json(['error' => 'No se envió el campo "nivel"'], Response::HTTP_BAD_REQUEST);
    }

    // ========================================================================
    // 3. BORRAR UN IDIOMA DEL PERFIL (DELETE)
    // ========================================================================
    #[Route('/voluntarios/{idVoluntario}/idiomas/{idIdioma}', name: 'delete', methods: ['DELETE'])]
    #[OA\Response(response: 200, description: 'Idioma desasignado')]
    public function delete(
        int $idVoluntario,
        int $idIdioma,
        EntityManagerInterface $entityManager,
        VoluntarioRepository $voluntarioRepo,
        IdiomaRepository $idiomaRepo
    ): JsonResponse {

        $voluntario = $voluntarioRepo->findOneBy(['usuario' => $idVoluntario]);
        $idioma = $idiomaRepo->find($idIdioma);

        if (!$voluntario || !$idioma) {
            return $this->json(['error' => 'Recurso no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $relacion = $entityManager->getRepository(VoluntarioIdioma::class)->findOneBy([
            'voluntario' => $voluntario,
            'idioma' => $idioma
        ]);

        if (!$relacion) {
            return $this->json(['error' => 'El voluntario no tiene este idioma asignado'], Response::HTTP_NOT_FOUND);
        }

        $entityManager->remove($relacion);
        $entityManager->flush();

        return $this->json(['mensaje' => 'Idioma eliminado del perfil'], Response::HTTP_OK);
    }
}
