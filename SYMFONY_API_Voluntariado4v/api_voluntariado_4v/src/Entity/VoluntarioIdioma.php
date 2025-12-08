<?php

namespace App\Entity;

use App\Repository\VoluntarioIdiomaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VoluntarioIdiomaRepository::class)]
#[ORM\Table(name: 'VOLUNTARIO_IDIOMA')]
class VoluntarioIdioma
{
    // Usamos una ID propia autogenerada para simplificar gestión en Symfony
    // aunque en SQL sea una clave compuesta, Doctrine prefiere esto.
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Relación con VOLUNTARIO
    #[ORM\ManyToOne(targetEntity: Voluntario::class)]
    #[ORM\JoinColumn(name: 'id_voluntario', referencedColumnName: 'id_usuario', nullable: false, onDelete: 'CASCADE')]
    private ?Voluntario $voluntario = null;

    // Relación con IDIOMA
    #[ORM\ManyToOne(targetEntity: Idioma::class)]
    #[ORM\JoinColumn(name: 'id_idioma', referencedColumnName: 'id_idioma', nullable: false)]
    private ?Idioma $idioma = null;

    // El campo extra: NIVEL
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $nivel = null; // Ej: A1, B2, Nativo

    // Getters y Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVoluntario(): ?Voluntario
    {
        return $this->voluntario;
    }
    public function setVoluntario(?Voluntario $voluntario): static
    {
        $this->voluntario = $voluntario;
        return $this;
    }

    public function getIdioma(): ?Idioma
    {
        return $this->idioma;
    }
    public function setIdioma(?Idioma $idioma): static
    {
        $this->idioma = $idioma;
        return $this;
    }

    public function getNivel(): ?string
    {
        return $this->nivel;
    }
    public function setNivel(?string $nivel): static
    {
        $this->nivel = $nivel;
        return $this;
    }
}
