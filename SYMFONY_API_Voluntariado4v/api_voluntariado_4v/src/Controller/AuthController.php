<?php

namespace App\Controller;

use App\Repository\UsuarioRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route('/api/auth', name: 'api_auth_')]
#[OA\Tag(name: 'Autenticación', description: 'Login y gestión de acceso')]
final class AuthController extends AbstractController
{
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
                new OA\Property(property: 'estado', type: 'string'),
                new OA\Property(property: 'token', type: 'string', description: 'Aquí iría el JWT en un futuro')
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Usuario no registrado (Redirigir a Registro)')]
    #[OA\Response(response: 403, description: 'Cuenta bloqueada o pendiente de aprobación')]
    public function login(Request $request, UsuarioRepository $usuarioRepository): JsonResponse
    {
        // 1. Recibir datos
        $data = json_decode($request->getContent(), true);
        $googleId = $data['google_id'] ?? null;
        $email = $data['email'] ?? null; 

        if (!$googleId) {
            return $this->json(['mensaje' => 'Falta el google_id'], Response::HTTP_BAD_REQUEST);
        }

        // 2. Buscar Usuario (Prioridad ID > Email)
        $usuario = $usuarioRepository->findOneBy(['googleId' => $googleId]);
        
        if (!$usuario && $email) {
            $usuario = $usuarioRepository->findOneBy(['correo' => $email]);
        }

        // 3. Semáforo de Acceso

        // ROJO: No existe -> 404 (Frontend redirige a Registro)
        if (!$usuario) {
            return $this->json(['mensaje' => 'Usuario no registrado. Requiere registro.'], Response::HTTP_NOT_FOUND);
        }

        // NEGRO: Cuenta eliminada o bloqueada -> 403 Forbidden
        if ($usuario->getDeletedAt() !== null || $usuario->getEstadoCuenta() === 'Bloqueada') {
            return $this->json([
                'error' => 'Acceso denegado',
                'mensaje' => 'Tu cuenta ha sido bloqueada o eliminada. Contacta con soporte.'
            ], Response::HTTP_FORBIDDEN);
        }

        // AMARILLO: Cuenta pendiente (Organizaciones) -> 403 Forbidden (con mensaje amable)
        if ($usuario->getEstadoCuenta() === 'Pendiente') {
            return $this->json([
                'error' => 'Cuenta no verificada',
                'mensaje' => 'Tu solicitud está en revisión. Te avisaremos cuando se active.'
            ], Response::HTTP_FORBIDDEN);
        }

        // VERDE: Acceso concedido
        return $this->json([
            'id_usuario' => $usuario->getId(),
            'google_id'  => $usuario->getGoogleId(),
            'correo'     => $usuario->getCorreo(),
            'rol'        => $usuario->getRol() ? $usuario->getRol()->getNombre() : 'User', 
            'estado'     => $usuario->getEstadoCuenta()
        ], Response::HTTP_OK);
    }
}