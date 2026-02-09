<?php

namespace App\Model\Actividad;

use App\Entity\Actividad;
use OpenApi\Attributes as OA;
use App\Model\Ods\OdsDTO;
use App\Model\TipoVoluntariado\TipoVoluntariadoDTO;

class ActividadResponseDTO
{
    public function __construct(
        #[OA\Property(example: 10)]
        public int $id,
        #[OA\Property(example: "Limpieza de Playa")]
        public string $titulo,
        #[OA\Property(example: "Recogida de plásticos en la costa")]
        public ?string $descripcion,
        #[OA\Property(example: "2026-06-15 09:00:00")]
        public string $fecha_inicio,
        #[OA\Property(example: 4)]
        public int $duracion_horas,
        #[OA\Property(example: 50)]
        public int $cupo_maximo,
        #[OA\Property(example: 12)]
        public int $inscritos_confirmados,
        #[OA\Property(example: 5)]
        public int $inscritos_pendientes,
        #[OA\Property(example: "Playa de la Barceloneta")]
        public string $ubicacion,
        #[OA\Property(example: "Publicada")]
        public string $estado_publicacion,
        #[OA\Property(example: 5)]
        public int $id_organizacion,
        #[OA\Property(example: "ONG Mar Limpio")]
        public string $nombre_organizacion,
        #[OA\Property(example: "logo_ong_5.jpg", nullable: true)]
        public ?string $img_organizacion,
        /** @var OdsDTO[] */
        #[OA\Property(type: 'array', items: new OA\Items(properties: [new OA\Property(property: 'id', example: 1), new OA\Property(property: 'nombre', example: 'Fin de la pobreza')]))]
        public array $ods = [],
        /** @var TipoVoluntariadoDTO[] */
        #[OA\Property(type: 'array', items: new OA\Items(properties: [new OA\Property(property: 'id', example: 1), new OA\Property(property: 'nombre', example: 'Social')]))]
        public array $tipos = [],
        #[OA\Property(example: "act_10_img.jpg", nullable: true)]
        public ?string $imagen_actividad = null,
        #[OA\Property(example: "/uploads/actividades/act_10_img.jpg", nullable: true)]
        public ?string $img_url = null
    ) {}

    public static function fromEntity(Actividad $act, ?string $baseUrl = null): self
    {
        $odsList = [];
        foreach ($act->getOds() as $o) {
            $odsList[] = [
                'id' => $o->getId(),
                'nombre' => $o->getNombre(),
                'img_url' => $o->getImgOds() ? ($baseUrl ? $baseUrl . '/uploads/ods/' . $o->getImgOds() : '/uploads/ods/' . $o->getImgOds()) : null
            ];
        }

        $tiposList = [];
        foreach ($act->getTiposVoluntariado() as $t) {
            $tiposList[] = ['id' => $t->getId(), 'nombre' => $t->getNombreTipo()];
        }

        $org = $act->getOrganizacion();
        $usuOrg = $org ? $org->getUsuario() : null;

        $inscritosConfirmados = 0;
        $inscritosPendientes = 0;
        foreach ($act->getInscripciones() as $insc) {
            if ($insc->getEstadoSolicitud() === 'Confirmada' || $insc->getEstadoSolicitud() === 'Aceptada') {
                $inscritosConfirmados++;
            } elseif ($insc->getEstadoSolicitud() === 'Pendiente') {
                $inscritosPendientes++;
            }
        }

        $imgOrg = $usuOrg ? $usuOrg->getImgPerfil() : null;
        if ($imgOrg && !str_starts_with($imgOrg, 'http')) {
            $imgOrg = $baseUrl ? $baseUrl . '/uploads/usuarios/' . $imgOrg : '/uploads/usuarios/' . $imgOrg;
        }

        return new self(
            id: $act->getId(),
            titulo: $act->getTitulo(),
            descripcion: $act->getDescripcion(),
            fecha_inicio: $act->getFechaInicio() ? $act->getFechaInicio()->format('Y-m-d H:i:s') : '',
            duracion_horas: $act->getDuracionHoras(),
            cupo_maximo: $act->getCupoMaximo(),
            inscritos_confirmados: $inscritosConfirmados,
            inscritos_pendientes: $inscritosPendientes,
            ubicacion: $act->getUbicacion() ?? 'No definida',
            estado_publicacion: $act->getEstadoPublicacion(),
            id_organizacion: $org ? $org->getId() : 0,
            nombre_organizacion: $org ? $org->getNombre() : 'Desconocida',
            img_organizacion: $imgOrg,
            ods: $odsList,
            tipos: $tiposList,
            imagen_actividad: $act->getImgActividad(),
            img_url: $act->getImgActividad() ? ($baseUrl ? $baseUrl . '/uploads/actividades/' . $act->getImgActividad() : '/uploads/actividades/' . $act->getImgActividad()) : null
        );
    }
}
