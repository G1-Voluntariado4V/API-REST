<?php

namespace App\Controller;

use App\Repository\UsuarioRepository;
use App\Repository\VoluntarioRepository;
use App\Repository\OrganizacionRepository;
use App\Repository\CoordinadorRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('/auth', name: 'api_auth_')]
#[OA\Tag(name: 'Autenticación', description: 'Login y recuperación de acceso')]
final class AuthController extends AbstractController
{
    // ========================================================================
    // 1. LOGIN (POST)
    // ========================================================================
    #[Route('/login', name: 'login', methods: ['POST'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'google_id', type: 'string', example: '1122334455'),
                new OA\Property(property: 'email', type: 'string', example: 'usuario@gmail.com')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Login exitoso',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id_usuario', type: 'integer'),
                new OA\Property(property: 'rol', type: 'string'),
                new OA\Property(property: 'estado_cuenta', type: 'string'),
                new OA\Property(property: 'nombre', type: 'string'),
                new OA\Property(property: 'apellidos', type: 'string')
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Usuario no registrado')]
    #[OA\Response(response: 403, description: 'Acceso denegado (Bloqueado/Pendiente)')]
    public function login(
        Request $request,
        UsuarioRepository $usuarioRepository,
        VoluntarioRepository $voluntarioRepository,
        OrganizacionRepository $organizacionRepository,
        CoordinadorRepository $coordinadorRepository
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            $googleId = $data['google_id'] ?? null;
            $email = $data['email'] ?? null;

            if (!$googleId && !$email) {
                return $this->json(['mensaje' => 'Falta el google_id o email'], Response::HTTP_BAD_REQUEST);
            }

            $usuario = null;

            if ($googleId) {
                $usuario = $usuarioRepository->findOneBy(['googleId' => $googleId]);
            }

            if (!$usuario && $email) {
                $usuario = $usuarioRepository->findOneBy(['correo' => $email]);
            }

            if (!$usuario) {
                return $this->json(['mensaje' => 'Usuario no registrado.'], Response::HTTP_NOT_FOUND);
            }

            $deletedAt = $usuario->getDeletedAt();
            if ($deletedAt !== null) {
                return $this->json(['mensaje' => 'Esta cuenta ha sido eliminada.'], Response::HTTP_FORBIDDEN);
            }

            $estadoCuenta = $usuario->getEstadoCuenta();

            if ($estadoCuenta === 'Bloqueada') {
                return $this->json(['mensaje' => 'Tu cuenta ha sido bloqueada. Contacta con el administrador.'], Response::HTTP_FORBIDDEN);
            }

            if ($estadoCuenta === 'Rechazada') {
                return $this->json(['mensaje' => 'Tu solicitud de registro fue rechazada.'], Response::HTTP_FORBIDDEN);
            }

            if ($estadoCuenta === 'Pendiente') {
                return $this->json([
                    'mensaje' => 'Tu cuenta está en revisión. Por favor, espera a que sea aprobada.',
                    'estado_cuenta' => 'Pendiente'
                ], Response::HTTP_FORBIDDEN);
            }

            if (!$usuario->getGoogleId() && $googleId) {
                $usuario->setGoogleId($googleId);
                $usuarioRepository->getEntityManager()->flush();
            }

            $rol = $usuario->getRol() ? $usuario->getRol()->getNombre() : 'Usuario';
            $nombre = null;
            $apellidos = null;
            $telefono = null;
            $datosExtra = [];

            $rolNormalizado = strtolower(str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $rol));

            if (strpos($rolNormalizado, 'voluntar') !== false) {
                $voluntario = $voluntarioRepository->findOneBy(['usuario' => $usuario]);
                if ($voluntario) {
                    $nombre = $voluntario->getNombre();
                    $apellidos = $voluntario->getApellidos();
                    $telefono = $voluntario->getTelefono();
                    $datosExtra['dni'] = $voluntario->getDni();
                    $curso = $voluntario->getCursoActual();
                    $datosExtra['curso'] = $curso ? $curso->getNombre() : null;
                }
            } elseif (strpos($rolNormalizado, 'organiz') !== false) {
                $organizacion = $organizacionRepository->findOneBy(['usuario' => $usuario]);
                if ($organizacion) {
                    $nombre = $organizacion->getNombre();
                    $telefono = $organizacion->getTelefono();
                    $datosExtra['cif'] = $organizacion->getCif();
                    $datosExtra['descripcion'] = $organizacion->getDescripcion();
                }
            } elseif (strpos($rolNormalizado, 'coordin') !== false) {
                $coordinador = $coordinadorRepository->findOneBy(['usuario' => $usuario]);
                if ($coordinador) {
                    $nombre = $coordinador->getNombre();
                    $apellidos = $coordinador->getApellidos();
                    $telefono = $coordinador->getTelefono();
                }
            }

            $response = [
                'id_usuario' => $usuario->getId(),
                'google_id'  => $usuario->getGoogleId(),
                'correo'     => $usuario->getCorreo(),
                'rol'        => $rol,
                'estado_cuenta' => $estadoCuenta,
                'nombre'     => $nombre,
                'apellidos'  => $apellidos,
                'telefono'   => $telefono
            ];

            foreach ($datosExtra as $key => $value) {
                $response[$key] = $value;
            }

            return $this->json($response, Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'mensaje' => 'Error interno del servidor',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
