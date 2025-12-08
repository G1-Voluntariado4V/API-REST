<?php

namespace App\Entity;

use App\Repository\TipoVoluntariadoRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TipoVoluntariadoRepository::class)]
class TipoVoluntariado
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['curso:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Groups(['curso:read'])]
    private ?string $nombreTipo = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombreTipo(): ?string
    {
        return $this->nombreTipo;
    }

    public function setNombreTipo(string $nombreTipo): static
    {
        $this->nombreTipo = $nombreTipo;

        return $this;
    }
}
