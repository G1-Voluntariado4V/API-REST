<?php

namespace App\Entity;

use App\Repository\CursoRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CursoRepository::class)]
#[ORM\Table(name: 'CURSO')] // <--- Nombre de tabla en BBDD
class Curso
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_curso')] // <--- Tu PK
    #[Groups(['curso:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100, name: 'nombre_curso')] // <--- Nombre columna SQL
    #[Groups(['curso:read'])]
    private ?string $nombre = null;

    #[ORM\Column(length: 10, name: 'abreviacion_curso')] // <--- Nombre columna SQL
    #[Groups(['curso:read'])]
    private ?string $abreviacion = null;

    #[ORM\Column(length: 50, name: 'grado')] // <--- Nombre columna SQL
    #[Groups(['curso:read'])]
    private ?string $grado = null;

    #[ORM\Column(name: 'nivel')] // <--- Nombre columna SQL
    #[Groups(['curso:read'])]
    private ?int $nivel = null;

    // --- GETTERS Y SETTERS ---

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

    public function getAbreviacion(): ?string
    {
        return $this->abreviacion;
    }

    public function setAbreviacion(string $abreviacion): static
    {
        $this->abreviacion = $abreviacion;
        return $this;
    }

    public function getGrado(): ?string
    {
        return $this->grado;
    }

    public function setGrado(string $grado): static
    {
        $this->grado = $grado;
        return $this;
    }

    public function getNivel(): ?int
    {
        return $this->nivel;
    }

    public function setNivel(int $nivel): static
    {
        $this->nivel = $nivel;
        return $this;
    }
}
