<?php

namespace App\Entity;

use App\Repository\ImagenActividadRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ImagenActividadRepository::class)]
#[ORM\Table(name: 'IMAGEN_ACTIVIDAD')]
class ImagenActividad
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_imagen')]
    #[Groups(['actividad:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, name: 'url_imagen')]
    #[Groups(['actividad:read'])]
    private ?string $urlImagen = null;

    #[ORM\Column(length: 255, nullable: true, name: 'descripcion_pie_foto')]
    #[Groups(['actividad:read'])]
    private ?string $descripcionPieFoto = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, name: 'id_actividad', referencedColumnName: 'id_actividad', onDelete: 'CASCADE')]
    private ?Actividad $actividad = null;

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getUrlImagen(): ?string
    {
        return $this->urlImagen;
    }
    public function setUrlImagen(string $urlImagen): static
    {
        $this->urlImagen = $urlImagen;
        return $this;
    }
    public function getDescripcionPieFoto(): ?string
    {
        return $this->descripcionPieFoto;
    }
    public function setDescripcionPieFoto(?string $descripcionPieFoto): static
    {
        $this->descripcionPieFoto = $descripcionPieFoto;
        return $this;
    }
    public function getActividad(): ?Actividad
    {
        return $this->actividad;
    }
    public function setActividad(?Actividad $actividad): static
    {
        $this->actividad = $actividad;
        return $this;
    }
}
