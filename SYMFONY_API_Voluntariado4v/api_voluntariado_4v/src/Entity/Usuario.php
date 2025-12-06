<?php

namespace App\Entity;

use App\Repository\UsuarioRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: UsuarioRepository::class)]
#[ORM\Table(name: 'USUARIO')] // <--- Nombre Tabla como en SQL
class Usuario
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_usuario')] // <--- Nombre Columna como en SQL y PK
    private ?int $id = null;

    #[ORM\Column(length: 100, name: 'correo')] // <--- Nombre Columna como en SQL
    private ?string $correo = null;

    #[ORM\Column(length: 255, name: 'google_id')] // <--- Nombre Columna como en SQL
    private ?string $googleId = null;

    #[ORM\Column(length: 500, nullable: true, name: 'refresh_token')] // <--- Nombre Columna como en SQL
    private ?string $refreshToken = null;

    // Inicializamos la fecha en el constructor para simular el DEFAULT GETDATE()
    #[ORM\Column(type: Types::DATETIME_MUTABLE, name: 'fecha_registro')]
    private ?\DateTime $fechaRegistro = null;

    #[ORM\Column(length: 20, name: 'estado_cuenta')] // <--- Nombre Columna como en SQL
    private ?string $estadoCuenta = null;

    // Campos de auditoría (opcionales en PHP si los gestiona el Trigger, pero bueno tenerlos)
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, name: 'updated_at')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, name: 'deleted_at')]
    private ?\DateTimeInterface $deletedAt = null;


    // Relación con la tabla ROL

    #[ORM\ManyToOne(targetEntity: Rol::class)]
    #[ORM\JoinColumn(
        nullable: false,
        name: 'id_rol',              // <--- Nombre de la columna en la tabla USUARIO
        referencedColumnName: 'id_rol' // <--- Nombre de la PK en la tabla ROL
    )]
    private ?Rol $rol = null;


    public function __construct()
    {
        // Esto equivale al DEFAULT GETDATE() de SQL
        $this->fechaRegistro = new \DateTime();
        $this->estadoCuenta = 'Pendiente';
    }

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

    public function getFechaRegistro(): ?\DateTime
    {
        return $this->fechaRegistro;
    }

    public function setFechaRegistro(\DateTime $fechaRegistro): static
    {
        $this->fechaRegistro = $fechaRegistro;

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
