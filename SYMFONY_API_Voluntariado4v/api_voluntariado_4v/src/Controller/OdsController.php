<?php

namespace App\Controller;

use App\Repository\ODSRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('', name: 'api_')]
#[OA\Tag(name: 'ODS', description: 'Gestión de Objetivos de Desarrollo Sostenible')]
final class OdsController extends AbstractController
{
    // ========================================================================
    // 1. LISTAR TODOS LOS ODS (GET)
    // ========================================================================
    #[Route('/ods', name: 'listar_ods', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Lista de todos los Objetivos de Desarrollo Sostenible',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 1),
                    new OA\Property(property: 'nombre', type: 'string', example: 'Fin de la pobreza'),
                    new OA\Property(property: 'descripcion', type: 'string', example: 'Poner fin a la pobreza en todas sus formas...'),
                    new OA\Property(property: 'imgOds', type: 'string', nullable: true, example: 'ods_1_abc123.png'),
                    new OA\Property(property: 'imgUrl', type: 'string', nullable: true, example: '/uploads/ods/ods_1_abc123.png')
                ],
                type: 'object'
            )
        )
    )]
    public function index(ODSRepository $repo): JsonResponse
    {
        return $this->json($repo->findAll(), 200, [
            'Cache-Control' => 'public, max-age=3600'
        ], ['groups' => 'ods:read']);
    }

    // ========================================================================
    // 2. SUBIR/ACTUALIZAR IMAGEN DE ODS (POST)
    // ========================================================================
    #[Route('/ods/{id}/imagen', name: 'upload_imagen_ods', methods: ['POST'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(
                        property: 'imagen',
                        type: 'string',
                        format: 'binary',
                        description: 'Archivo de imagen (jpg, jpeg, png, webp). Máximo 5MB.'
                    )
                ]
            )
        )
    )]
    #[OA\Response(response: 200, description: 'Imagen de ODS actualizada correctamente')]
    #[OA\Response(response: 400, description: 'Error en la validación del archivo')]
    #[OA\Response(response: 404, description: 'ODS no encontrado')]
    #[OA\Response(response: 500, description: 'Error de escritura en disco')]
    public function uploadImagen(
        int $id,
        Request $request,
        ODSRepository $odsRepo,
        EntityManagerInterface $em,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%uploads_directory%')] string $uploadsDirectory
    ): JsonResponse {
        $ods = $odsRepo->find($id);
        if (!$ods) {
            return $this->json(['error' => 'ODS no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $file = $request->files->get('imagen');
        if (!$file) {
            return $this->json(['error' => 'No se ha enviado ningún archivo en el campo "imagen"'], Response::HTTP_BAD_REQUEST);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($extension, $allowedExtensions)) {
            return $this->json([
                'error' => 'Formato de imagen no soportado. Permitidos: ' . implode(', ', $allowedExtensions)
            ], Response::HTTP_BAD_REQUEST);
        }

        $maxSize = 5 * 1024 * 1024;
        if ($file->getSize() > $maxSize) {
            return $this->json(['error' => 'La imagen supera el tamaño máximo permitido (5MB)'], Response::HTTP_BAD_REQUEST);
        }

        $targetDirectory = $uploadsDirectory . '/ods';
        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0777, true);
        }

        $filename = uniqid('ods_' . $id . '_') . '.' . $extension;
        try {
            $file->move($targetDirectory, $filename);
        } catch (\Symfony\Component\HttpFoundation\File\Exception\FileException $e) {
            return $this->json([
                'error' => 'Error al guardar la imagen en el servidor: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $oldImage = $ods->getImgOds();
        if ($oldImage) {
            $oldPath = $uploadsDirectory . '/ods/' . $oldImage;
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }

        $ods->setImgOds($filename);
        $em->persist($ods);
        $em->flush();

        return $this->json([
            'mensaje' => 'Imagen de ODS actualizada correctamente',
            'img_ods' => $filename,
            'img_url' => '/uploads/ods/' . $filename
        ], Response::HTTP_OK);
    }

    // ========================================================================
    // 3. ELIMINAR IMAGEN DE ODS (DELETE)
    // ========================================================================
    #[Route('/ods/{id}/imagen', name: 'delete_imagen_ods', methods: ['DELETE'])]
    #[OA\Response(response: 200, description: 'Imagen de ODS eliminada correctamente')]
    #[OA\Response(response: 404, description: 'ODS no encontrado o sin imagen')]
    public function deleteImagen(
        int $id,
        ODSRepository $odsRepo,
        EntityManagerInterface $em,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%uploads_directory%')] string $uploadsDirectory
    ): JsonResponse {
        $ods = $odsRepo->find($id);
        if (!$ods) {
            return $this->json(['error' => 'ODS no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $currentImage = $ods->getImgOds();
        if (!$currentImage) {
            return $this->json(['mensaje' => 'El ODS no tiene imagen asignada'], Response::HTTP_OK);
        }

        $imagePath = $uploadsDirectory . '/ods/' . $currentImage;
        if (file_exists($imagePath)) {
            @unlink($imagePath);
        }

        $ods->setImgOds(null);
        $em->persist($ods);
        $em->flush();

        return $this->json(['mensaje' => 'Imagen de ODS eliminada correctamente'], Response::HTTP_OK);
    }
    // ========================================================================
    // 4. CREAR NUEVO ODS (POST)
    // ========================================================================
    #[Route('/ods', name: 'crear_ods', methods: ['POST'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'nombre', type: 'string'),
                new OA\Property(property: 'descripcion', type: 'string'),
                new OA\Property(property: 'numero', type: 'integer', example: 1)
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'ODS creado correctamente')]
    #[OA\Response(response: 400, description: 'Datos inválidos')]
    public function create(Request $request, EntityManagerInterface $em, ODSRepository $repo): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['nombre']) || !isset($data['descripcion'])) {
            return $this->json(['error' => 'Faltan datos obligatorios (nombre, descripcion)'], Response::HTTP_BAD_REQUEST);
        }

        $ods = new \App\Entity\ODS();
        $ods->setNombre($data['nombre']);
        $ods->setDescripcion($data['descripcion']);

        $em->persist($ods);
        $em->flush();

        return $this->json($ods, Response::HTTP_CREATED, [], ['groups' => 'ods:read']);
    }

    // ========================================================================
    // 5. ACTUALIZAR ODS (PUT)
    // ========================================================================
    #[Route('/ods/{id}', name: 'update_ods', methods: ['PUT'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'nombre', type: 'string'),
                new OA\Property(property: 'descripcion', type: 'string')
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'ODS actualizado')]
    #[OA\Response(response: 404, description: 'ODS no encontrado')]
    public function update(int $id, Request $request, ODSRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $ods = $repo->find($id);
        if (!$ods) {
            return $this->json(['error' => 'ODS no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['nombre'])) {
            $ods->setNombre($data['nombre']);
        }
        if (isset($data['descripcion'])) {
            $ods->setDescripcion($data['descripcion']);
        }

        $em->flush();

        return $this->json($ods, Response::HTTP_OK, [], ['groups' => 'ods:read']);
    }

    // ========================================================================
    // 6. ELIMINAR ODS (DELETE)
    // ========================================================================
    #[Route('/ods/{id}', name: 'delete_ods', methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'ODS eliminado')]
    #[OA\Response(response: 404, description: 'ODS no encontrado')]
    public function delete(int $id, ODSRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $ods = $repo->find($id);
        if (!$ods) {
            return $this->json(['error' => 'ODS no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($ods);
        $em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
