<?php

namespace App\Entity;

use App\Repository\OrganizacionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OrganizacionRepository::class)]
#[ORM\Table(name: 'ORGANIZACION')]
class Organizacion
{
    #[ORM\Id]
    #[ORM\Column(name: 'id_usuario', type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Usuario::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(
        name: 'id_usuario',
        referencedColumnName: 'id_usuario',
        nullable: false,
        onDelete: 'CASCADE'
    )]
    #[Groups(['usuario:read'])]
    private ?Usuario $usuario = null;

    // --- DATOS DE LA EMPRESA / ONG ---

    #[ORM\Column(length: 20, unique: true, nullable: true)]
    #[Groups(['usuario:read'])] // El CIF suele ser dato privado/administrativo, mejor no mostrarlo en actividades públicas
    private ?string $cif = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['usuario:read', 'actividad:read'])]
    private ?string $nombre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['usuario:read', 'actividad:read'])]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'La descripción no puede superar los {{ limit }} caracteres.'
    )]
    private ?string $descripcion = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['usuario:read'])]
    #[Assert\Length(
        max: 255,
        maxMessage: 'La dirección es demasiado larga (máx. {{ limit }} caracteres).'
    )]
    private ?string $direccion = null;

    #[ORM\Column(length: 200, nullable: true, name: 'sitio_web')]
    #[Groups(['usuario:read', 'actividad:read'])] // <--- Útil para enlazar
    #[Assert\Url(requireTld: true)]
    private ?string $sitioWeb = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['usuario:read'])]
    private ?string $telefono = null;


    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, name: 'updated_at')]
    private ?\DateTimeInterface $updatedAt = null;

    // ... (El resto de Getters y Setters se queda igual) ...

    // --- GETTERS Y SETTERS ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsuario(): ?Usuario
    {
        return $this->usuario;
    }

    public function setUsuario(Usuario $usuario): static
    {
        $this->usuario = $usuario;
        if ($usuario->getId()) {
            $this->id = $usuario->getId();
        }
        return $this;
    }

    public function getCif(): ?string
    {
        return $this->cif;
    }

    public function setCif(?string $cif): static
    {
        $this->cif = $cif;
        return $this;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(?string $nombre): static
    {
        $this->nombre = $nombre;
        return $this;
    }

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(?string $descripcion): static
    {
        $this->descripcion = $descripcion;
        return $this;
    }

    public function getDireccion(): ?string
    {
        return $this->direccion;
    }

    public function setDireccion(?string $direccion): static
    {
        $this->direccion = $direccion;
        return $this;
    }

    public function getSitioWeb(): ?string
    {
        return $this->sitioWeb;
    }

    public function setSitioWeb(?string $sitioWeb): static
    {
        $this->sitioWeb = $sitioWeb;
        return $this;
    }

    public function getTelefono(): ?string
    {
        return $this->telefono;
    }

    public function setTelefono(?string $telefono): static
    {
        $this->telefono = $telefono;
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
}
