<?php

namespace App\Entity;

use App\Repository\InscripcionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: InscripcionRepository::class)]
#[ORM\Table(name: 'INSCRIPCION')]
class Inscripcion
{
    // --- CLAVE COMPUESTA (Voluntario + Actividad) ---

    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'inscripciones')]
    #[ORM\JoinColumn(name: 'id_voluntario', referencedColumnName: 'id_usuario', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['actividad:read'])] // Para ver quién se ha apuntado cuando pides la actividad
    private ?Voluntario $voluntario = null;

    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'inscripciones')]
    #[ORM\JoinColumn(name: 'id_actividad', referencedColumnName: 'id_actividad', nullable: false)]
    #[Groups(['usuario:read'])] // Para ver "Mis Inscripciones" cuando pides el voluntario
    private ?Actividad $actividad = null;

    // --- DATOS PROPIOS DE LA INSCRIPCIÓN ---

    #[ORM\Column(type: Types::DATETIME_MUTABLE, name: 'fecha_solicitud')]
    #[Groups(['usuario:read', 'actividad:read'])]
    private ?\DateTimeInterface $fechaSolicitud = null;

    #[ORM\Column(length: 20, name: 'estado_solicitud', options: ['default' => 'Pendiente'])]
    #[Groups(['usuario:read', 'actividad:read'])]
    private ?string $estadoSolicitud = 'Pendiente';

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, name: 'updated_at')]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->fechaSolicitud = new \DateTime();
    }

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

    public function getActividad(): ?Actividad
    {
        return $this->actividad;
    }
    public function setActividad(?Actividad $actividad): static
    {
        $this->actividad = $actividad;
        return $this;
    }

    public function getFechaSolicitud(): ?\DateTimeInterface
    {
        return $this->fechaSolicitud;
    }
    public function setFechaSolicitud(\DateTimeInterface $fechaSolicitud): static
    {
        $this->fechaSolicitud = $fechaSolicitud;
        return $this;
    }

    public function getEstadoSolicitud(): ?string
    {
        return $this->estadoSolicitud;
    }
    public function setEstadoSolicitud(string $estadoSolicitud): static
    {
        $this->estadoSolicitud = $estadoSolicitud;
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
}
