<?php

namespace App\Model\Inscripcion;

use Symfony\Component\Validator\Constraints as Assert;

class InscripcionUpdateDTO
{
    public function __construct(
        #[Assert\NotBlank(message: "El estado es obligatorio")]
        #[Assert\Choice(
            choices: ['Aceptada', 'Rechazada', 'Pendiente'],
            message: "El estado debe ser 'Aceptada', 'Rechazada' o 'Pendiente'"
        )]
        public string $estado
    ) {}
}
