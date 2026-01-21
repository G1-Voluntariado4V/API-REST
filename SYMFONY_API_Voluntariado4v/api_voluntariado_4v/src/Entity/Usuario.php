<?php

namespace App\Entity;

use App\Repository\UsuarioRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: UsuarioRepository::class)]
#[ORM\Table(name: 'USUARIO')]
class Usuario implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_usuario')]
    #[Groups(['usuario:read', 'actividad:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Groups(['usuario:read'])]
    private ?string $correo = null;

    #[ORM\Column(length: 255, unique: true, name: 'google_id')]
    #[Groups(['usuario:read'])]
    private ?string $googleId = null;

    #[ORM\Column(length: 500, nullable: true, name: 'refresh_token')]
    private ?string $refreshToken = null;

    #[ORM\Column(length: 20, name: 'estado_cuenta', options: ['default' => 'Pendiente'])]
    #[Groups(['usuario:read'])]
    private ?string $estadoCuenta = 'Pendiente';

    #[ORM\Column(length: 255, nullable: true, name: 'img_perfil')]
    #[Groups(['usuario:read'])]
    private ?string $imgPerfil = null;



    // Relación con tu tabla ROL
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, name: 'id_rol', referencedColumnName: 'id_rol')]
    #[Groups(['usuario:read'])]
    private ?Rol $rol = null;

    // Fechas de auditoría
    #[ORM\Column(type: Types::DATETIME_MUTABLE, name: 'fecha_registro')]
    private ?\DateTimeInterface $fechaRegistro = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, name: 'updated_at')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(nullable: true, name: 'deleted_at')]
    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct()
    {
        $this->fechaRegistro = new \DateTime();
    }

    // --- GETTERS Y SETTERS PROPIOS ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCorreo(): ?string
    {
        return $this->correo;
    }
    public function setCorreo(string $correo): static
    {
        $this->correo = $correo;
        return $this;
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }
    public function setGoogleId(string $googleId): static
    {
        $this->googleId = $googleId;
        return $this;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }
    public function setRefreshToken(?string $refreshToken): static
    {
        $this->refreshToken = $refreshToken;
        return $this;
    }

    public function getEstadoCuenta(): ?string
    {
        return $this->estadoCuenta;
    }
    public function setEstadoCuenta(string $estadoCuenta): static
    {
        $this->estadoCuenta = $estadoCuenta;
        return $this;
    }

    public function getImgPerfil(): ?string
    {
        return $this->imgPerfil;
    }
    public function setImgPerfil(?string $imgPerfil): static
    {
        $this->imgPerfil = $imgPerfil;
        return $this;
    }

    /**
     * Getter virtual (NO mapeado a BBDD) que devuelve la URL pública de la imagen de perfil.
     * Se incluye en la serialización JSON con el grupo 'usuario:read'.
     */
    #[Groups(['usuario:read'])]
    public function getImgPerfilUrl(): ?string
    {
        return $this->imgPerfil ? '/uploads/usuarios/' . $this->imgPerfil : null;
    }

    public function getRol(): ?Rol
    {
        return $this->rol;
    }
    public function setRol(?Rol $rol): static
    {
        $this->rol = $rol;
        return $this;
    }



    public function getFechaRegistro(): ?\DateTimeInterface
    {
        return $this->fechaRegistro;
    }
    public function setFechaRegistro(\DateTimeInterface $fechaRegistro): static
    {
        $this->fechaRegistro = $fechaRegistro;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }
    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }
    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    // --- MÉTODOS OBLIGATORIOS DE SYMFONY (UserInterface) ---

    /**
     * Identificador visual del usuario (el correo).
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->correo;
    }

    /**
     * Solución al error "Object could not be converted to string"
     */
    public function __toString(): string
    {
        return (string) $this->correo;
    }

    /**
     * Convierte tu ROL de SQL (tabla) a un ROLE de Symfony (array).
     */
    public function getRoles(): array
    {
        $nombreRol = $this->rol ? $this->rol->getNombre() : 'User';
        return ['ROLE_' . strtoupper($nombreRol)];
    }

    /**
     * Limpia datos sensibles temporales (obligatorio por interfaz, aunque esté vacío).
     */
    public function eraseCredentials(): void {}
}
