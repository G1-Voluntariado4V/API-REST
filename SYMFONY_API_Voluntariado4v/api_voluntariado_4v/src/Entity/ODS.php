<?php

namespace App\Entity;

use App\Repository\ODSRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ODSRepository::class)]
class ODS
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id_ods')]
    #[Groups(['actividad:read', 'curso:read', 'ods:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Groups(['actividad:read', 'curso:read', 'ods:read'])]
    private ?string $nombre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['actividad:read', 'curso:read', 'ods:read'])]
    #[Assert\Length(
        max: 500,
        maxMessage: 'La descripción del ODS no puede superar los {{ limit }} caracteres.'
    )]
    private ?string $descripcion = null;

    #[ORM\Column(length: 255, nullable: true, name: 'img_ods')]
    #[Groups(['actividad:read', 'curso:read', 'ods:read'])]
    private ?string $imgOds = null;

    public function __construct() {}

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

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(?string $descripcion): static
    {
        $this->descripcion = $descripcion;
        return $this;
    }

    public function getImgOds(): ?string
    {
        return $this->imgOds;
    }

    public function setImgOds(?string $imgOds): static
    {
        $this->imgOds = $imgOds;
        return $this;
    }

    #[Groups(['actividad:read', 'curso:read', 'ods:read'])]
    public function getImgUrl(): ?string
    {
        return $this->imgOds ? '/uploads/ods/' . $this->imgOds : null;
    }
}
