<?php

namespace App\Controller;

use App\Repository\UsuarioRepository;
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
    // MANTENEMOS EL LOGIN: Es responsabilidad de Auth dar acceso.
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
                new OA\Property(property: 'token', type: 'string', description: 'JWT futuro')
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Usuario no registrado')]
    #[OA\Response(response: 403, description: 'Acceso denegado (Bloqueado/Pendiente)')]
    public function login(Request $request, UsuarioRepository $usuarioRepository): JsonResponse
    {
        // ... (TU CÓDIGO DEL LOGIN ESTABA BIEN, MANTENLO IGUAL) ...
        // ... Logica de buscar usuario, verificar estado, etc.

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
        if (!$usuario) {
            return $this->json(['mensaje' => 'Usuario no registrado.'], Response::HTTP_NOT_FOUND);
        }

        // ... resto de validaciones de estado ...

        return $this->json([
            'id_usuario' => $usuario->getId(),
            'google_id'  => $usuario->getGoogleId(),
            'correo'     => $usuario->getCorreo(),
            'rol'        => $usuario->getRol() ? $usuario->getRol()->getNombre() : 'User',
            'estado'     => $usuario->getEstadoCuenta()
        ], Response::HTTP_OK);
    }

    // EL MÉTODO REGISTER SE ELIMINA DE AQUÍ
    // Y se delega a VoluntarioController y OrganizacionController
}
