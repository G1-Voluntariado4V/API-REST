<?php

namespace App\Model\Voluntario;

use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

class VoluntarioCreateDTO
{
    public function __construct(
        #[Assert\NotBlank(message: "El Google ID es obligatorio")]
        #[OA\Property(example: "google_usr_99")]
        public string $google_id,

        #[Assert\NotBlank]
        #[Assert\Email]
        #[OA\Property(example: "voluntario@test.com")]
        public string $correo,

        #[Assert\NotBlank]
        #[Assert\Length(min: 2)]
        #[OA\Property(example: "Ana")]
        public string $nombre,

        #[Assert\NotBlank]
        #[OA\Property(example: "García")]
        public string $apellidos,

        #[Assert\NotBlank]
        #[OA\Property(example: "12345678Z")]
        public string $dni,

        #[Assert\NotBlank]
        #[OA\Property(example: "+34 655 44 33 22")]
        public string $telefono,

        #[Assert\NotBlank]
        #[Assert\Date]
        #[OA\Property(example: "1999-12-31")]
        public string $fecha_nac,

        #[OA\Property(example: true)]
        public bool $carnet_conducir = false,

        #[Assert\NotBlank]
        #[OA\Property(example: 2)]
        public int $id_curso_actual,

        #[Assert\Length(max: 500, maxMessage: "La descripción no puede tener más de 500 caracteres")]
        #[OA\Property(example: "Me gusta ayudar con temas de logística.")]
        public ?string $descripcion = null,

        #[OA\Property(example: [1, 3], type: 'array', items: new OA\Items(type: 'integer'))]
        public array $preferencias_ids = [],

        #[OA\Property(
            example: [['id_idioma' => 1, 'nivel' => 'B2']],
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'id_idioma', type: 'integer'),
                    new OA\Property(property: 'nivel', type: 'string')
                ]
            )
        )]
        public array $idiomas = []
    ) {}
}
