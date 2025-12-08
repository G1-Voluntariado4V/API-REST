<?php

namespace App\Entity;

use App\Repository\VoluntarioIdiomaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: VoluntarioIdiomaRepository::class)]
#[ORM\Table(name: 'VOLUNTARIO_IDIOMA')]
class VoluntarioIdioma
{
   #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['usuario:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['usuario:read'])]
    private ?string $nivel = null;

    // Relación: Muchos "VoluntarioIdioma" pertenecen a Un "Voluntario"
    #[ORM\ManyToOne(inversedBy: 'voluntarioIdiomas')]
    #[ORM\JoinColumn(nullable: false, name: 'id_voluntario', referencedColumnName: 'id_usuario', onDelete: 'CASCADE')]
    private ?Voluntario $voluntario = null;

    // Relación: Muchos "VoluntarioIdioma" apuntan a Un "Idioma"
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, name: 'id_idioma', referencedColumnName: 'id_idioma')]
    #[Groups(['usuario:read'])]
    private ?Idioma $idioma = null;

    public function getId(): ?int { return $this->id; }

    public function getNivel(): ?string { return $this->nivel; }
    public function setNivel(?string $nivel): static { $this->nivel = $nivel; return $this; }

    public function getVoluntario(): ?Voluntario { return $this->voluntario; }
    public function setVoluntario(?Voluntario $voluntario): static { $this->voluntario = $voluntario; return $this; }

    public function getIdioma(): ?Idioma { return $this->idioma; }
    public function setIdioma(?Idioma $idioma): static { $this->idioma = $idioma; return $this; }
}
