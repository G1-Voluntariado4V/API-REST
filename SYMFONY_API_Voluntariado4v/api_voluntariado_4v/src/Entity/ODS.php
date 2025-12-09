<?php

namespace App\Entity;

use App\Repository\ODSRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ODSRepository::class)]
class ODS
{
   #[ORM\Id]
    #[ORM\Column(name: 'id_ods')]
    // 👇 AQUÍ ESTÁ EL TRUCO: Ponemos los dos grupos
    #[Groups(['actividad:read', 'curso:read'])] 
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    // 👇 AQUÍ TAMBIÉN
    #[Groups(['actividad:read', 'curso:read'])] 
    private ?string $nombre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['actividad:read', 'curso:read'])]
    private ?string $descripcion = null;

    // --- CONSTRUCTOR OPCIONAL (Recomendado para IDs obligatorios) ---
    public function __construct(int $id, string $nombre)
    {
        $this->id = $id;
        $this->nombre = $nombre;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    // --- IMPORTANTE: Añadir este Setter si no usas el constructor ---
    public function setId(int $id): static
    {
        $this->id = $id;
        return $this;
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

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(?string $descripcion): static
    {
        $this->descripcion = $descripcion;
        return $this;
    }
}
