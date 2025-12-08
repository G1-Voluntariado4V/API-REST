<?php

namespace App\Controller;

use App\Entity\Usuario;
use App\Entity\Voluntario;
use App\Repository\RolRepository;
use App\Repository\CursoRepository;
use App\Entity\Idioma;
use App\Repository\UsuarioRepository;
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


    // [MÉTODO NUEVO] ACTUALIZAR (PUT)
    #[Route('/voluntarios/{id}', name: 'actualizar_voluntario', methods: ['PUT'])]
    public function actualizar(
        int $id,
        Request $request,
        UsuarioRepository $usuarioRepo, // Buscamos por Usuario ID (que es la PK)
        IdiomaRepository $idiomaRepo,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        // 1. Buscar al voluntario por su ID de Usuario (que es su PK)
        $usuario = $usuarioRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], 404);
        
        // Obtenemos el perfil de voluntario asociado
        // (Asumiendo que tienes la relación inversa getVoluntario() en Usuario, 
        //  si no, buscamos en el repositorio de Voluntario)
        $voluntario = $entityManager->getRepository(Voluntario::class)->findOneBy(['usuario' => $usuario]);

        if (!$voluntario) return $this->json(['error' => 'Perfil de voluntario no encontrado'], 404);

        $data = json_decode($request->getContent(), true);

        // 2. Actualizar Idiomas (Caso Ana: Inglés a C1)
        if (!empty($data['idiomas']) && is_array($data['idiomas'])) {
            foreach ($data['idiomas'] as $idiomaData) {
                $idiomaEntity = $idiomaRepo->find($idiomaData['id_idioma']);
                if ($idiomaEntity) {
                    // Buscamos si ya tiene este idioma asignado
                    $relacionExistente = null;
                    foreach ($voluntario->getVoluntarioIdiomas() as $vi) {
                        if ($vi->getIdioma()->getId() === $idiomaEntity->getId()) {
                            $relacionExistente = $vi;
                            break;
                        }
                    }

                    if ($relacionExistente) {
                        // ACTUALIZAMOS nivel
                        $relacionExistente->setNivel($idiomaData['nivel']);
                    } else {
                        // CREAMOS relación nueva
                        $nuevoIdioma = new VoluntarioIdioma();
                        $nuevoIdioma->setVoluntario($voluntario);
                        $nuevoIdioma->setIdioma($idiomaEntity);
                        $nuevoIdioma->setNivel($idiomaData['nivel']);
                        $entityManager->persist($nuevoIdioma);
                    }
                }
            }
        }

        // Aquí podrías añadir lógica para actualizar nombre, apellidos, etc.

        $entityManager->flush();

        return $this->json($voluntario, 200, [], ['groups' => 'usuario:read']);
    }

    // [MÉTODO NUEVO] BORRAR (DELETE - Soft Delete)
    #[Route('/voluntarios/{id}', name: 'borrar_voluntario', methods: ['DELETE'])]
    public function eliminar(
        int $id, 
        UsuarioRepository $usuarioRepo, 
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $usuario = $usuarioRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], 404);

        // Aplicamos Soft Delete manualmente para ser explícitos en el código
        // (Aunque tu trigger de BBDD también protege, es mejor hacerlo desde la APP)
        $usuario->setDeletedAt(new \DateTimeImmutable());
        $usuario->setEstadoCuenta('Bloqueada'); // O 'Inactiva'

        $entityManager->flush();

        return $this->json(['mensaje' => 'Usuario eliminado correctamente (Soft Delete)'], 200);
    }
}
