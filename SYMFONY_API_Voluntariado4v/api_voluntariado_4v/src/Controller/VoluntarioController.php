<?php

namespace App\Controller;

use App\Entity\Usuario;
use App\Entity\Voluntario;
use App\Repository\RolRepository;
use App\Repository\CursoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
final class VoluntarioController extends AbstractController
{
    #[Route('/voluntarios', name: 'registro_voluntario', methods: ['POST'])]
    public function registrar(
        Request $request,
        EntityManagerInterface $entityManager,
        RolRepository $rolRepository,
        CursoRepository $cursoRepository // Inyección de dependencia para buscar cursos
    ): JsonResponse {

        // 1. Decodificar el JSON que envía el Frontend
        $data = json_decode($request->getContent(), true);

        // 2. Validaciones básicas (Campos obligatorios)
        // Nota: DNI y Teléfono son opcionales en la BBDD, pero nombre/apellidos/email son vitales.
        if (!isset($data['google_id'], $data['correo'], $data['nombre'], $data['apellidos'])) {
            return $this->json(['error' => 'Faltan datos obligatorios (google_id, correo, nombre, apellidos)'], 400);
        }

        // 3. Iniciar Transacción (Seguridad de Datos)
        // Si falla la creación del Voluntario, se deshace la creación del Usuario.
        $entityManager->beginTransaction();

        try {
            // ======================================================
            // PASO A: Crear el USUARIO (Cuenta base para Login)
            // ======================================================
            $usuario = new Usuario();
            $usuario->setCorreo($data['correo']);
            $usuario->setGoogleId($data['google_id']);

            // Asignar Rol: Buscamos el ID 2 (Voluntario)
            $rolVoluntario = $rolRepository->find(2);
            if (!$rolVoluntario) {
                throw new \Exception("El rol de Voluntario (ID 2) no existe en la base de datos.");
            }
            $usuario->setRol($rolVoluntario);

            // Guardamos el usuario primero para que SQL Server genere su ID
            $entityManager->persist($usuario);
            $entityManager->flush();

            // ======================================================
            // PASO B: Crear el VOLUNTARIO (Perfil detallado)
            // ======================================================
            $voluntario = new Voluntario();

            // Vinculación 1 a 1: El voluntario usa el ID del usuario recién creado
            $voluntario->setUsuario($usuario);

            // Datos Personales básicos
            $voluntario->setNombre($data['nombre']);
            $voluntario->setApellidos($data['apellidos']);
            $voluntario->setTelefono($data['telefono'] ?? null); // Operador ?? por si viene null
            $voluntario->setDni($data['dni'] ?? null);

            // --- CAMPOS AVANZADOS ---

            // 1. Carnet Conducir (Boolean)
            $voluntario->setCarnetConducir($data['carnet_conducir'] ?? false);

            // 2. Fecha Nacimiento (String 'YYYY-MM-DD' -> DateTime)
            if (!empty($data['fecha_nac'])) {
                try {
                    // Intentamos convertir el string a objeto fecha
                    $voluntario->setFechaNac(new \DateTime($data['fecha_nac']));
                } catch (\Exception $e) {
                    // Si la fecha es inválida, se queda en null (no bloqueamos el registro)
                }
            }

            // 3. Imagen de Perfil (String URL de Firebase)
            $voluntario->setImgPerfil($data['img_perfil'] ?? null);

            // 4. Curso Actual (Relación ManyToOne)
            if (!empty($data['id_curso_actual'])) {
                $curso = $cursoRepository->find($data['id_curso_actual']);
                if ($curso) {
                    $voluntario->setCursoActual($curso);
                } else {
                    // Opcional: Podríamos lanzar error si el curso ID no existe
                    // throw new \Exception("El curso especificado no existe");
                }
            }

            $entityManager->persist($voluntario);
            $entityManager->flush();

            // ======================================================
            // PASO C: Confirmar todo (Commit)
            // ======================================================
            $entityManager->commit();

            return $this->json([
                'mensaje' => 'Voluntario registrado correctamente',
                'id_usuario' => $usuario->getId(),
                'curso' => $voluntario->getCursoActual()?->getNombre() // Devolvemos info extra útil
            ], 201);
        } catch (\Exception $e) {
            // ¡ERROR CRÍTICO! Deshacemos todo lo hecho en BBDD
            $entityManager->rollback();

            return $this->json(['error' => 'Error al registrar: ' . $e->getMessage()], 500);
        }
    }
}
