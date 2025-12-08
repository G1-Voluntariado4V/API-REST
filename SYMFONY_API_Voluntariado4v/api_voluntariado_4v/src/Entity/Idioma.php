<?php

namespace App\Entity;

use App\Repository\IdiomaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: IdiomaRepository::class)]
#[ORM\Table(name: 'IDIOMA')]
class Idioma
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_idioma')]
    #[Groups(['idioma:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 50, name: 'nombre_idioma')]
    #[Groups(['idioma:read'])]
    private ?string $nombre = null;

    #[ORM\Column(length: 3, nullable: true, name: 'codigo_iso')]
    #[Groups(['idioma:read'])]
    private ?string $codigoIso = null;

    // Getters y Setters
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
    public function getCodigoIso(): ?string
    {
        return $this->codigoIso;
    }
    public function setCodigoIso(?string $codigoIso): static
    {
        $this->codigoIso = $codigoIso;
        return $this;
    }
}
