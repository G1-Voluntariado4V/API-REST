<?php

namespace App\Entity;

use App\Repository\VoluntarioIdiomaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: VoluntarioIdiomaRepository::class)]
#[ORM\Table(name: 'VOLUNTARIO_IDIOMA')]
class VoluntarioIdioma
{
    // ------------------------------------------------------------------------
    // PARTE 1 DE LA CLAVE PRIMARIA (Voluntario)
    // ------------------------------------------------------------------------
    #[ORM\Id] // <--- Indica que esto es parte de la PK
    #[ORM\ManyToOne(inversedBy: 'voluntarioIdiomas')]
    #[ORM\JoinColumn(
        name: 'id_voluntario',
        referencedColumnName: 'id_usuario',
        nullable: false,
        onDelete: 'CASCADE'
    )]
    private ?Voluntario $voluntario = null;

    // ------------------------------------------------------------------------
    // PARTE 2 DE LA CLAVE PRIMARIA (Idioma)
    // ------------------------------------------------------------------------
    #[ORM\Id] // <--- Indica que esto TAMBIÉN es parte de la PK
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(
        name: 'id_idioma',
        referencedColumnName: 'id_idioma',
        nullable: false
    )]
    #[Groups(['usuario:read'])] // Para que se vea el detalle del idioma (nombre, iso)
    private ?Idioma $idioma = null;

    // ------------------------------------------------------------------------
    // DATOS EXTRA
    // ------------------------------------------------------------------------
    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['usuario:read'])]
    private ?string $nivel = null;

    // --- GETTERS Y SETTERS ---

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
