<?php

namespace App\Controller;

use App\Entity\Usuario;
use App\Entity\Voluntario;
use App\Repository\RolRepository;
use App\Repository\CursoRepository;
use App\Entity\Idioma;
use App\Entity\VoluntarioIdioma;
use App\Repository\IdiomaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface; // <--- 1. IMPORTE NECESARIO

#[Route('/api', name: 'api_')]
final class VoluntarioController extends AbstractController
{
    #[Route('/voluntarios', name: 'registro_voluntario', methods: ['POST'])]
    public function registrar(
        Request $request,
        EntityManagerInterface $entityManager,
        RolRepository $rolRepository,
        CursoRepository $cursoRepository,
        IdiomaRepository $idiomaRepository,
        UserPasswordHasherInterface $hasher // <--- 2. INYECCIÓN DEL HASHER
    ): JsonResponse {

        // 1. Decodificar el JSON
        $data = json_decode($request->getContent(), true);

        // 2. Validaciones básicas
        if (!isset($data['google_id'], $data['correo'], $data['nombre'], $data['apellidos'])) {
            return $this->json(['error' => 'Faltan datos obligatorios'], 400);
        }

        // 3. Transacción
        $entityManager->beginTransaction();

        try {
            // ======================================================
            // PASO A: Crear el USUARIO
            // ======================================================
            $usuario = new Usuario();
            $usuario->setCorreo($data['correo']);
            $usuario->setGoogleId($data['google_id']);

            // --- AÑADIDO: CORRECCIÓN DE SEGURIDAD BBDD ---
            // Estos campos son NOT NULL en la base de datos, hay que rellenarlos
            $usuario->setEstadoCuenta('Pendiente');
            $passwordAleatoria = bin2hex(random_bytes(10)); // Genera algo como "a1b2c3d4e5"
            $usuario->setPassword($hasher->hashPassword($usuario, $passwordAleatoria));
            // ---------------------------------------------

            // Asignar Rol Voluntario (ID 2)
            $rolVoluntario = $rolRepository->find(2);
            if (!$rolVoluntario) throw new \Exception("Rol Voluntario no encontrado");

            $usuario->setRol($rolVoluntario);

            $entityManager->persist($usuario);
            $entityManager->flush();

            // ======================================================
            // PASO B: Crear el VOLUNTARIO
            // ======================================================
            $voluntario = new Voluntario();
            $voluntario->setUsuario($usuario);
            $voluntario->setNombre($data['nombre']);
            $voluntario->setApellidos($data['apellidos']);
            $voluntario->setTelefono($data['telefono'] ?? null);
            $voluntario->setDni($data['dni'] ?? null);
            $voluntario->setCarnetConducir($data['carnet_conducir'] ?? false);

            // Fecha nacimiento
            if (!empty($data['fecha_nac'])) {
                try {
                    $voluntario->setFechaNac(new \DateTime($data['fecha_nac']));
                } catch (\Exception $e) {
                }
            }

            $voluntario->setImgPerfil($data['img_perfil'] ?? null);

            // Curso
            if (!empty($data['id_curso_actual'])) {
                $curso = $cursoRepository->find($data['id_curso_actual']);
                if ($curso) $voluntario->setCursoActual($curso);
            }

            // Idiomas
            if (!empty($data['idiomas']) && is_array($data['idiomas'])) {
                foreach ($data['idiomas'] as $idiomaData) {
                    $idiomaEntity = $idiomaRepository->find($idiomaData['id_idioma']);
                    if ($idiomaEntity) {
                        $voluntarioIdioma = new VoluntarioIdioma();
                        $voluntarioIdioma->setVoluntario($voluntario);
                        $voluntarioIdioma->setIdioma($idiomaEntity);
                        $voluntarioIdioma->setNivel($idiomaData['nivel'] ?? 'Básico');
                        $entityManager->persist($voluntarioIdioma);
                    }
                }
            }

            $entityManager->persist($voluntario);
            $entityManager->flush();

            $entityManager->commit();

            return $this->json($voluntario, 201, [], ['groups' => 'usuario:read']);
        } catch (\Exception $e) {
            $entityManager->rollback();
            return $this->json(['error' => 'Error al registrar: ' . $e->getMessage()], 500);
        }
    }
}
