<?php

namespace App\Controller;

use App\Repository\UsuarioRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth', name: 'api_auth_')]
final class AuthController extends AbstractController
{
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request, UsuarioRepository $usuarioRepository): JsonResponse
    {
        // 1. Recibir el JSON del Frontend
        $data = json_decode($request->getContent(), true);
        $googleId = $data['google_id'] ?? null;


        // Validación básica
        if (!$googleId) {
            return $this->json(['mensaje' => 'Falta el google_id'], 400);
        }

        // 2. Buscar en la BBDD por google_id
        // Doctrine hace la magia: SELECT * FROM USUARIO WHERE google_id = '...'
        $usuario = $usuarioRepository->findOneBy(['googleId' => $googleId]);


        // 3. Lógica de Negocio (El Semáforo)

        // ROJO: El usuario no existe en nuestra BBDD
        if (!$usuario) {
            // Devolvemos 404 para que el Frontend sepa que tiene que redirigir al Registro
            return $this->json(['mensaje' => 'Usuario no registrado. Requiere registro.'], 404);
        }

        // VERDE: El usuario existe -> Devolvemos sus datos y su ROL
        return $this->json([
            'id_usuario' => $usuario->getId(),
            'google_id'  => $usuario->getGoogleId(),
            'correo'     => $usuario->getCorreo(),
            'rol'        => $usuario->getRol()->getNombre(),
            'estado'     => $usuario->getEstadoCuenta()
        ], 200);
    }
}
