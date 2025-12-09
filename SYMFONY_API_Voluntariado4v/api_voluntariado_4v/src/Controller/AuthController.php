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
        $email = $data['email'] ?? null; // Recogemos también el email por si acaso

        // Validación básica
        if (!$googleId) {
            return $this->json(['mensaje' => 'Falta el google_id'], 400);
        }

        // 2. Buscar en la BBDD
        // Prioridad: Buscar por Google ID
        $usuario = $usuarioRepository->findOneBy(['googleId' => $googleId]);

        // Fallback: Si no encuentra por ID, intentamos por correo (por seguridad)
        if (!$usuario && $email) {
            $usuario = $usuarioRepository->findOneBy(['correo' => $email]);
        }

        // 3. Lógica de Negocio (El Semáforo)

        // ROJO: El usuario no existe en nuestra BBDD
        if (!$usuario) {
            // Devolvemos 404 para que el Frontend sepa que tiene que redirigir al Registro
            return $this->json(['mensaje' => 'Usuario no registrado. Requiere registro.'], 404);
        }

        // --- 🛑 NUEVA VALIDACIÓN DE ESTADO (Para Org y Voluntarios) ---
        
        // A. Verificar si está eliminado (Soft Delete) o Bloqueado manualmente
        if ($usuario->getDeletedAt() !== null || $usuario->getEstadoCuenta() === 'Bloqueada') {
            return $this->json([
                'error' => 'Acceso denegado',
                'mensaje' => 'Tu cuenta ha sido bloqueada o eliminada. Contacta con soporte.'
            ], 403);
        }

        // B. Verificar si está Pendiente (Caso típico de Organizaciones nuevas)
        if ($usuario->getEstadoCuenta() === 'Pendiente') {
            return $this->json([
                'error' => 'Cuenta no verificada',
                'mensaje' => 'Tu solicitud está siendo revisada por un administrador. Te avisaremos cuando se active.'
            ], 403);
        }

        // --------------------------------------------------------------

        // VERDE: El usuario existe y está ACTIVO -> Devolvemos sus datos y su ROL
        return $this->json([
            'id_usuario' => $usuario->getId(),
            'google_id'  => $usuario->getGoogleId(),
            'correo'     => $usuario->getCorreo(),
            // Obtenemos el nombre del rol de la entidad relacionada
            'rol'        => $usuario->getRol() ? $usuario->getRol()->getNombre() : 'User', 
            'estado'     => $usuario->getEstadoCuenta()
        ], 200);
    }
}