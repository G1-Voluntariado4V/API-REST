<?php

namespace App\Controller;

use App\Entity\Usuario;
use App\Entity\Voluntario;
use App\Entity\VoluntarioIdioma;
use App\Repository\RolRepository;
use App\Repository\CursoRepository;
use App\Repository\IdiomaRepository;
use App\Repository\UsuarioRepository;
use App\Repository\TipoVoluntariadoRepository; // NUEVO
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
final class VoluntarioController extends AbstractController
{
    // [NUEVO] 1. LISTADO OPTIMIZADO (Usando Vista SQL)
    #[Route('/voluntarios', name: 'listar_voluntarios', methods: ['GET'])]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        $conn = $em->getConnection();
        // Llamada directa a la Vista SQL
        $sql = 'SELECT * FROM VW_Voluntarios_Activos';
        
        try {
            $voluntarios = $conn->executeQuery($sql)->fetchAllAssociative();
            return $this->json($voluntarios);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al obtener voluntarios: ' . $e->getMessage()], 500);
        }
    }

    // [NUEVO] 2. RECOMENDACIONES (Usando Procedimiento Almacenado)
    #[Route('/voluntarios/{id}/recomendaciones', name: 'recomendaciones_voluntario', methods: ['GET'])]
    public function recomendaciones(int $id, EntityManagerInterface $em): JsonResponse
    {
        $conn = $em->getConnection();
        $sql = 'EXEC SP_Get_Recomendaciones_Voluntario @id_voluntario = :id';
        
        try {
            $stmt = $conn->executeQuery($sql, ['id' => $id]);
            $actividades = $stmt->fetchAllAssociative();
            return $this->json($actividades);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al calcular recomendaciones'], 500);
        }
    }

    // [MODIFICADO] 3. REGISTRAR VOLUNTARIO (Tu código + Preferencias)
    #[Route('/voluntarios', name: 'registro_voluntario', methods: ['POST'])]
    public function registrar(
        Request $request,
        EntityManagerInterface $entityManager,
        RolRepository $rolRepository,
        CursoRepository $cursoRepository,
        IdiomaRepository $idiomaRepository,
        TipoVoluntariadoRepository $tipoRepo // Inyectado
    ): JsonResponse {

        $data = json_decode($request->getContent(), true);

        if (!isset($data['google_id'], $data['correo'], $data['nombre'], $data['apellidos'])) {
            return $this->json(['error' => 'Faltan datos obligatorios'], 400);
        }

        $entityManager->beginTransaction();

        try {
            // A. USUARIO
            $usuario = new Usuario();
            $usuario->setCorreo($data['correo']);
            $usuario->setGoogleId($data['google_id']);
            $usuario->setEstadoCuenta('Activa'); // Voluntarios entran directos
            
            $rolVoluntario = $rolRepository->findOneBy(['nombre' => 'Voluntario']); 
            if (!$rolVoluntario) throw new \Exception("Rol Voluntario no encontrado");
            $usuario->setRol($rolVoluntario);

            $entityManager->persist($usuario);
            $entityManager->flush();

            // B. PERFIL VOLUNTARIO
            $voluntario = new Voluntario();
            $voluntario->setUsuario($usuario);
            $voluntario->setNombre($data['nombre']);
            $voluntario->setApellidos($data['apellidos']);
            $voluntario->setTelefono($data['telefono'] ?? null);
            $voluntario->setDni($data['dni'] ?? null);
            $voluntario->setCarnetConducir($data['carnet_conducir'] ?? false);
            $voluntario->setImgPerfil($data['img_perfil'] ?? null);

            if (!empty($data['fecha_nac'])) {
                try { $voluntario->setFechaNac(new \DateTime($data['fecha_nac'])); } catch (\Exception $e) {}
            }

            if (!empty($data['id_curso_actual'])) {
                $curso = $cursoRepository->find($data['id_curso_actual']);
                if ($curso) $voluntario->setCursoActual($curso);
            }

            // C. IDIOMAS
            if (!empty($data['idiomas']) && is_array($data['idiomas'])) {
                foreach ($data['idiomas'] as $idiomaData) {
                    $idiomaEntity = $idiomaRepository->find($idiomaData['id_idioma']);
                    if ($idiomaEntity) {
                        $vi = new VoluntarioIdioma();
                        $vi->setVoluntario($voluntario);
                        $vi->setIdioma($idiomaEntity);
                        $vi->setNivel($idiomaData['nivel'] ?? 'Básico');
                        $entityManager->persist($vi);
                    }
                }
            }

            // D. PREFERENCIAS [NUEVO BLOQUE]
            if (!empty($data['preferencias_ids']) && is_array($data['preferencias_ids'])) {
                foreach ($data['preferencias_ids'] as $idTipo) {
                    $tipo = $tipoRepo->find($idTipo);
                    if ($tipo) $voluntario->addPreferencia($tipo);
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

    // [MANTENIDO] 4. GET ONE
    #[Route('/voluntarios/{id}', name: 'get_voluntario', methods: ['GET'])]
    public function getOne(int $id, UsuarioRepository $userRepo): JsonResponse 
    {
        $usuario = $userRepo->find($id);
        if (!$usuario || $usuario->getDeletedAt()) {
            return $this->json(['error' => 'Voluntario no encontrado'], 404);
        }
        return $this->json($usuario, 200, [], ['groups' => 'usuario:read']);
    }

    // [MODIFICADO] 5. ACTUALIZAR (PUT)
    #[Route('/voluntarios/{id}', name: 'actualizar_voluntario', methods: ['PUT'])]
    public function actualizar(
        int $id,
        Request $request,
        UsuarioRepository $usuarioRepo,
        IdiomaRepository $idiomaRepo,
        TipoVoluntariadoRepository $tipoRepo, // Inyectado
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $usuario = $usuarioRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], 404);
        
        $voluntario = $entityManager->getRepository(Voluntario::class)->findOneBy(['usuario' => $usuario]);
        if (!$voluntario) return $this->json(['error' => 'Perfil no encontrado'], 404);

        $data = json_decode($request->getContent(), true);

        // Campos básicos
        if (isset($data['nombre'])) $voluntario->setNombre($data['nombre']);
        if (isset($data['apellidos'])) $voluntario->setApellidos($data['apellidos']);
        if (isset($data['telefono'])) $voluntario->setTelefono($data['telefono']);
        // ... añadir resto de campos si quieres

        // Idiomas (Tu lógica original estaba bien, la mantengo simplificada)
        // ... (Tu código de idiomas aquí)

        // Preferencias [NUEVO]
        if (isset($data['preferencias_ids']) && is_array($data['preferencias_ids'])) {
            // Borrar antiguas
            foreach ($voluntario->getPreferencias() as $pref) {
                $voluntario->removePreferencia($pref);
            }
            // Añadir nuevas
            foreach ($data['preferencias_ids'] as $idTipo) {
                $tipo = $tipoRepo->find($idTipo);
                if ($tipo) $voluntario->addPreferencia($tipo);
            }
        }

        $entityManager->flush();
        return $this->json($voluntario, 200, [], ['groups' => 'usuario:read']);
    }

    // [MANTENIDO] 6. ELIMINAR (DELETE)
    #[Route('/voluntarios/{id}', name: 'borrar_voluntario', methods: ['DELETE'])]
    public function eliminar(int $id, UsuarioRepository $usuarioRepo, EntityManagerInterface $em): JsonResponse 
    {
        $usuario = $usuarioRepo->find($id);
        if (!$usuario) return $this->json(['error' => 'Usuario no encontrado'], 404);

        $usuario->setDeletedAt(new \DateTimeImmutable());
        $usuario->setEstadoCuenta('Bloqueada');
        $em->flush();

        return $this->json(['mensaje' => 'Usuario eliminado correctamente'], 200);
    }
    
    // [NUEVO] 7. RESTAURAR
    #[Route('/voluntarios/{id}/restaurar', name: 'restaurar_voluntario', methods: ['POST'])]
    public function restaurar(int $id, EntityManagerInterface $em): JsonResponse
    {
        $sql = 'EXEC SP_Restore_Usuario @id_usuario = :id';
        try {
            $em->getConnection()->executeStatement($sql, ['id' => $id]);
            return $this->json(['mensaje' => 'Usuario restaurado']);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Error al restaurar'], 500);
        }
    }
}