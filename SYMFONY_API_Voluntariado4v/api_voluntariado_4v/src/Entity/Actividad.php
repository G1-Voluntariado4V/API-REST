<?php

namespace App\Entity;

use App\Repository\ActividadRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ActividadRepository::class)]
#[ORM\Table(name: 'ACTIVIDAD')]
class Actividad
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_actividad')]
    #[Groups(['actividad:read', 'usuario:read'])] // usuario:read para verlas dentro de la org
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Groups(['actividad:read', 'usuario:read'])]
    private ?string $titulo = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['actividad:read'])]
    private ?string $descripcion = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, name: 'fecha_inicio')]
    #[Groups(['actividad:read'])]
    private ?\DateTimeInterface $fechaInicio = null;

    #[ORM\Column(name: 'duracion_horas')]
    #[Groups(['actividad:read'])]
    private ?int $duracionHoras = null;

    #[ORM\Column(name: 'cupo_maximo')]
    #[Groups(['actividad:read'])]
    private ?int $cupoMaximo = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['actividad:read'])]
    private ?string $ubicacion = null;

    // Default según SQL: 'En revision'
    #[ORM\Column(length: 20, name: 'estado_publicacion', options: ['default' => 'En revision'])]
    #[Groups(['actividad:read'])]
    private ?string $estadoPublicacion = 'En revision';


    // --- RELACIONES ---

    // 1. ORGANIZACIÓN (Dueña de la actividad)
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, name: 'id_organizacion', referencedColumnName: 'id_usuario')]
    #[Groups(['actividad:read'])]
    private ?Organizacion $organizacion = null;

    // 2. ODS (Relación Muchos a Muchos con tabla intermedia específica)
    #[ORM\ManyToMany(targetEntity: ODS::class)]
    #[ORM\JoinTable(
        name: 'ACTIVIDAD_ODS',
        joinColumns: [new ORM\JoinColumn(name: 'id_actividad', referencedColumnName: 'id_actividad')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'id_ods', referencedColumnName: 'id_ods')] // Asumiendo que ODS usa 'id' en PHP, o 'id_ods' si lo cambiaste
    )]
    #[Groups(['actividad:read'])]
    private Collection $ods;

    // 3. TIPO VOLUNTARIADO (Relación Muchos a Muchos)
    #[ORM\ManyToMany(targetEntity: TipoVoluntariado::class)]
    #[ORM\JoinTable(
        name: 'ACTIVIDAD_TIPO',
        joinColumns: [new ORM\JoinColumn(name: 'id_actividad', referencedColumnName: 'id_actividad')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'id_tipo', referencedColumnName: 'id_tipo')]
    )]
    #[Groups(['actividad:read'])]
    private Collection $tiposVoluntariado;

    // --- TIMESTAMPS ---

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, name: 'updated_at')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(nullable: true, name: 'deleted_at')]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\OneToMany(mappedBy: 'actividad', targetEntity: Inscripcion::class, cascade: ['persist', 'remove'])]
    // OJO: No pongas Groups aquí o podrías crear un bucle infinito al serializar 
    private Collection $inscripciones;

    public function __construct()
    {
        $this->ods = new ArrayCollection();
        $this->tiposVoluntariado = new ArrayCollection();
        $this->inscripciones = new ArrayCollection();
    }

    // --- GETTERS Y SETTERS ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitulo(): ?string
    {
        return $this->titulo;
    }
    public function setTitulo(string $titulo): static
    {
        $this->titulo = $titulo;
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

    public function getFechaInicio(): ?\DateTimeInterface
    {
        return $this->fechaInicio;
    }
    public function setFechaInicio(\DateTimeInterface $fechaInicio): static
    {
        $this->fechaInicio = $fechaInicio;
        return $this;
    }

    public function getDuracionHoras(): ?int
    {
        return $this->duracionHoras;
    }
    public function setDuracionHoras(int $duracionHoras): static
    {
        $this->duracionHoras = $duracionHoras;
        return $this;
    }

    public function getCupoMaximo(): ?int
    {
        return $this->cupoMaximo;
    }
    public function setCupoMaximo(int $cupoMaximo): static
    {
        $this->cupoMaximo = $cupoMaximo;
        return $this;
    }

    public function getUbicacion(): ?string
    {
        return $this->ubicacion;
    }
    public function setUbicacion(?string $ubicacion): static
    {
        $this->ubicacion = $ubicacion;
        return $this;
    }

    public function getEstadoPublicacion(): ?string
    {
        return $this->estadoPublicacion;
    }
    public function setEstadoPublicacion(string $estadoPublicacion): static
    {
        $this->estadoPublicacion = $estadoPublicacion;
        return $this;
    }

    public function getOrganizacion(): ?Organizacion
    {
        return $this->organizacion;
    }
    public function setOrganizacion(?Organizacion $organizacion): static
    {
        $this->organizacion = $organizacion;
        return $this;
    }

    // Colecciones
    public function getOds(): Collection
    {
        return $this->ods;
    }
    public function addOd(ODS $od): static
    {
        if (!$this->ods->contains($od)) {
            $this->ods->add($od);
        }
        return $this;
    }
    public function removeOd(ODS $od): static
    {
        $this->ods->removeElement($od);
        return $this;
    }

    public function getTiposVoluntariado(): Collection
    {
        return $this->tiposVoluntariado;
    }
    public function addTiposVoluntariado(TipoVoluntariado $tipo): static
    {
        if (!$this->tiposVoluntariado->contains($tipo)) {
            $this->tiposVoluntariado->add($tipo);
        }
        return $this;
    }
    public function removeTiposVoluntariado(TipoVoluntariado $tipo): static
    {
        $this->tiposVoluntariado->removeElement($tipo);
        return $this;
    }


    public function getInscripciones(): Collection
    {
        return $this->inscripciones;
    }

    public function addInscripcion(Inscripcion $inscripcion): static
    {
        if (!$this->inscripciones->contains($inscripcion)) {
            $this->inscripciones->add($inscripcion);
            // Sincronizar el lado propietario
            $inscripcion->setActividad($this);
        }
        return $this;
    }

    public function removeInscripcion(Inscripcion $inscripcion): static
    {
        if ($this->inscripciones->removeElement($inscripcion)) {

            if ($inscripcion->getActividad() === $this) {
                $inscripcion->setActividad(null);
            }
        }
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }
}
