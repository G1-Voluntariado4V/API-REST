<?php

namespace App\Entity;

use App\Repository\VoluntarioRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Serializer\Annotation\Groups;
use Doctrine\Common\Collections\ArrayCollection; 
use Doctrine\Common\Collections\Collection;


#[ORM\Entity(repositoryClass: VoluntarioRepository::class)]
#[ORM\Table(name: 'VOLUNTARIO')] // <--- Nombre de tabla SQL
class Voluntario
{
    // --- PK COMPARTIDA CON USUARIO (1:1) ---
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: Usuario::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(
        name: 'id_usuario',
        referencedColumnName: 'id_usuario',
        nullable: false,
        onDelete: 'CASCADE' // <--- ¡ESTO ES LA CLAVE!
    )]
    #[Groups(['usuario:read'])]
    private ?Usuario $usuario = null;

    #[ORM\Column(length: 9, unique: true, nullable: true)]
    #[Groups(['usuario:read'])]
    private ?string $dni = null;

    #[ORM\Column(length: 50)]
    #[Groups(['usuario:read'])]
    private ?string $nombre = null;

    #[ORM\Column(length: 100)]
    #[Groups(['usuario:read'])]
    private ?string $apellidos = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['usuario:read'])]
    private ?string $telefono = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true, name: 'fecha_nac')]
    private ?\DateTimeInterface $fechaNac = null;

    // Nota: SQL dice 'bit', en Doctrine es 'boolean'
    #[ORM\Column(type: 'boolean', nullable: true, name: 'carnet_conducir')]
    private ?bool $carnetConducir = false;

    // --- LOS NUEVOS CAMPOS ---

    #[ORM\Column(length: 255, nullable: true, name: 'img_perfil')]
    private ?string $imgPerfil = null;

    // Relación con CURSO (id_curso_actual)
    #[ORM\ManyToOne(targetEntity: Curso::class)]
    #[ORM\JoinColumn(name: 'id_curso_actual', referencedColumnName: 'id_curso', nullable: true)]
    private ?Curso $cursoActual = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, name: 'updated_at')]
    private ?\DateTimeInterface $updatedAt = null;

    // --- NUEVA PROPIEDAD PARA IDIOMAS ---
    #[ORM\OneToMany(mappedBy: 'voluntario', targetEntity: VoluntarioIdioma::class, cascade: ['persist', 'remove'])]
    #[Groups(['usuario:read'])] // Para que salgan los idiomas al pedir el usuario
    private Collection $voluntarioIdiomas;

    public function __construct()
    {
        $this->voluntarioIdiomas = new ArrayCollection();
    }

    // --- GETTERS Y SETTERS ---

    public function getId(): ?int
    {
        return $this->usuario?->getId();
    }

    public function getUsuario(): ?Usuario
    {
        return $this->usuario;
    }
    public function setUsuario(Usuario $usuario): static
    {
        $this->usuario = $usuario;
        return $this;
    }

    public function getDni(): ?string
    {
        return $this->dni;
    }
    public function setDni(?string $dni): static
    {
        $this->dni = $dni;
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

    public function getApellidos(): ?string
    {
        return $this->apellidos;
    }
    public function setApellidos(string $apellidos): static
    {
        $this->apellidos = $apellidos;
        return $this;
    }

    public function getTelefono(): ?string
    {
        return $this->telefono;
    }
    public function setTelefono(?string $telefono): static
    {
        $this->telefono = $telefono;
        return $this;
    }

    public function getFechaNac(): ?\DateTimeInterface
    {
        return $this->fechaNac;
    }
    public function setFechaNac(?\DateTimeInterface $fechaNac): static
    {
        $this->fechaNac = $fechaNac;
        return $this;
    }

    public function isCarnetConducir(): ?bool
    {
        return $this->carnetConducir;
    }
    public function setCarnetConducir(?bool $carnetConducir): static
    {
        $this->carnetConducir = $carnetConducir;
        return $this;
    }

    public function getImgPerfil(): ?string
    {
        return $this->imgPerfil;
    }
    public function setImgPerfil(?string $imgPerfil): static
    {
        $this->imgPerfil = $imgPerfil;
        return $this;
    }

    public function getCursoActual(): ?Curso
    {
        return $this->cursoActual;
    }
    public function setCursoActual(?Curso $cursoActual): static
    {
        $this->cursoActual = $cursoActual;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }
    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return Collection<int, VoluntarioIdioma>
     */
    public function getVoluntarioIdiomas(): Collection
    {
        return $this->voluntarioIdiomas;
    }

    public function addVoluntarioIdioma(VoluntarioIdioma $vi): static
    {
        if (!$this->voluntarioIdiomas->contains($vi)) {
            $this->voluntarioIdiomas->add($vi);
            $vi->setVoluntario($this);
        }
        return $this;
    }

    public function removeVoluntarioIdioma(VoluntarioIdioma $vi): static
    {
        if ($this->voluntarioIdiomas->removeElement($vi)) {
            if ($vi->getVoluntario() === $this) {
                $vi->setVoluntario(null);
            }
        }
        return $this;
    }

}
