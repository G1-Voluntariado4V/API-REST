<?php

namespace App\Entity;

use App\Repository\UsuarioRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: UsuarioRepository::class)]
#[ORM\Table(name: 'USUARIO')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_CORREO', fields: ['correo'])]
class Usuario implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_usuario')] // Mapeo exacto a tu SQL
    #[Groups(['usuario:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180, name: 'correo')]
    #[Groups(['usuario:read'])]
    private ?string $correo = null;

    /**
     * @var list<string> The user roles
     * Symfony necesita esta propiedad, pero en BBDD la llamamos diferente
     * para que no estorbe, ya que usamos tu tabla ROL.
     */
    #[ORM\Column(name: 'roles_symfony_ignored')]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column(name: 'password')]
    private ?string $password = null;

    #[ORM\Column(length: 255, name: 'google_id')]
    #[Groups(['usuario:read'])]
    private ?string $googleId = null;

    #[ORM\Column(length: 20, name: 'estado_cuenta')]
    #[Groups(['usuario:read'])]
    private ?string $estadoCuenta = null;

    #[ORM\Column(length: 500, nullable: true, name: 'refresh_token')]
    private ?string $refreshToken = null;

    // --- CAMPOS DE FECHA (Estaban en tu SQL pero faltaban aquí) ---

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, name: 'fecha_registro')]
    #[Groups(['usuario:read'])]
    private ?\DateTimeInterface $fechaRegistro = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, name: 'updated_at')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(nullable: true, name: 'deleted_at')]
    private ?\DateTimeImmutable $deletedAt = null;

    // --- RELACIÓN CON ROL ---

    #[ORM\ManyToOne]
    // referencedColumnName: 'id' asume que en tu entidad Rol.php el ID se mapea normal.
    // Si en Rol.php cambiaste el nombre de la columna a 'id_rol', esto funcionará.
    #[ORM\JoinColumn(nullable: false, name: 'id_rol', referencedColumnName: 'id_rol')]
    #[Groups(['usuario:read'])]
    private ?Rol $rol = null;

    // --- CONSTRUCTOR (Para inicializar fecha registro) ---
    public function __construct()
    {
        $this->fechaRegistro = new \DateTime(); // PHP pone la fecha actual aquí
        // $this->roles = []; // Si tuvieras que inicializar arrays, iría aquí
    }

    // --- GETTERS Y SETTERS ---

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

    public function getUserIdentifier(): string
    {
        return (string) $this->correo;
    }

    /**
     * @see UserInterface
     * Lógica personalizada para usar tu tabla ROL
     */
    public function getRoles(): array
    {
        $nombreRol = $this->rol ? $this->rol->getNombre() : null;

        // Transformamos "Administrador" -> "ROLE_ADMINISTRADOR"
        if ($nombreRol) {
            // Quitamos acentos y espacios por seguridad si los hubiera
            // Pero para tu SQL simple, strtoupper basta.
            $rolSymfony = 'ROLE_' . strtoupper($nombreRol);
            $roles = [$rolSymfony, 'ROLE_USER'];
        } else {
            $roles = ['ROLE_USER'];
        }

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0" . self::class . "\0password"] = hash('crc32c', $this->password);
        return $data;
    }

    public function eraseCredentials(): void
    {
        // Si guardaras datos temporales sensibles, los borrarías aquí.
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

    public function getEstadoCuenta(): ?string
    {
        return $this->estadoCuenta;
    }

    public function setEstadoCuenta(string $estadoCuenta): static
    {
        $this->estadoCuenta = $estadoCuenta;
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

    public function getFechaRegistro(): ?\DateTimeInterface
    {
        return $this->fechaRegistro;
    }

    public function setFechaRegistro(?\DateTimeInterface $fechaRegistro): static
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

    public function getRol(): ?Rol
    {
        return $this->rol;
    }

    public function setRol(?Rol $rol): static
    {
        $this->rol = $rol;
        return $this;
    }
}
