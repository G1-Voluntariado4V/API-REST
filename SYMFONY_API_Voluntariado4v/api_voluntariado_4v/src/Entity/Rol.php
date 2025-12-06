<?php

namespace App\Entity;

use App\Repository\RolRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RolRepository::class)]
#[ORM\Table(name: 'ROL')] // <--- 1. Nombre Tabla en Mayúsculas como en SQL
class Rol
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_rol')] // <--- 2. PK exacta del SQL
    private ?int $id = null;

    #[ORM\Column(length: 50, name: 'nombre_rol')] // <--- 3. Columna exacta del SQL
    private ?string $nombre = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;

        return $this;
    }
}
