<?php

namespace App\Model\Inscripcion;

use App\Entity\Inscripcion;
use OpenApi\Attributes as OA;

class InscripcionResponseDTO
{
    public function __construct(
        #[OA\Property(example: "10-5")]
        public string $id,
        #[OA\Property(example: "Confirmada")]
        public string $estado,
        #[OA\Property(example: "2026-06-01 10:00:00")]
        public string $fecha_solicitud,
        #[OA\Property(example: 5)]
        public int $id_actividad,
        #[OA\Property(example: "Taller de Reciclaje")]
        public string $titulo_actividad,
        #[OA\Property(example: "Aprende a reciclar correctamente...")]
        public string $descripcion,
        #[OA\Property(example: "Centro Cívico")]
        public string $ubicacion,
        #[OA\Property(example: 3)]
        public int $duracion_horas,
        #[OA\Property(example: "2026-06-10 16:00:00")]
        public string $fecha_actividad,
        #[OA\Property(example: 2)]
        public int $id_organizacion,
        #[OA\Property(example: "Asociación Verde")]
        public string $nombre_organizacion,
        #[OA\Property(example: 20)]
        public int $cupo_maximo,
        #[OA\Property(example: 15)]
        public int $inscritos_confirmados,
        #[OA\Property(type: 'array', items: new OA\Items(properties: [new OA\Property(property: 'id', example: 12), new OA\Property(property: 'nombre', example: 'Consumo Responsable')]))]
        public array $ods,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'string', example: 'Ambiental'))]
        public array $tipos,
        #[OA\Property(example: 10)]
        public int $id_voluntario,
        #[OA\Property(example: "Juan Pérez")]
        public string $nombre_voluntario,
        #[OA\Property(example: "act_5.jpg", nullable: true)]
        public ?string $imagen_actividad = null
    ) {}

    public static function fromEntity(Inscripcion $ins): self
    {
        $act = $ins->getActividad();
        $vol = $ins->getVoluntario();
        $userVol = $vol->getUsuario();

        $compositeId = $userVol->getId() . '-' . $act->getId();

        $ods = [];
        foreach ($act->getOds() as $od) {
            $ods[] = [
                'id' => $od->getId(),
                'nombre' => $od->getNombre()
            ];
        }

        $tipos = [];
        foreach ($act->getTiposVoluntariado() as $tipo) {
            $tipos[] = $tipo->getNombreTipo();
        }

        $inscritosConfirmados = 0;
        if ($act->getInscripciones()) {
            foreach ($act->getInscripciones() as $otherIns) {
                if ($otherIns->getEstadoSolicitud() === 'Confirmada' || $otherIns->getEstadoSolicitud() === 'Aceptada') {
                    $inscritosConfirmados++;
                }
            }
        }

        return new self(
            $compositeId,
            $ins->getEstadoSolicitud(),
            $ins->getFechaSolicitud()->format('Y-m-d H:i:s'),

            $act->getId(),
            $act->getTitulo(),
            $act->getDescripcion() ?? '',
            $act->getUbicacion() ?? '',
            $act->getDuracionHoras(),
            $act->getFechaInicio()->format('Y-m-d H:i:s'),
            $act->getOrganizacion()->getId(),
            $act->getOrganizacion()->getNombre(),
            $act->getCupoMaximo(),
            $inscritosConfirmados,
            $ods,
            $tipos,

            $userVol->getId(),
            $vol->getNombre() . ' ' . $vol->getApellidos(),
            $act->getImgActividad()
        );
    }
}
