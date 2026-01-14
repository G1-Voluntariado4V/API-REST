<?php

namespace App\Model\Voluntario;

use Symfony\Component\Validator\Constraints as Assert;

class VoluntarioUpdateDTO
{
    #[Assert\NotBlank(message: "El nombre no puede estar vacío.")]
    #[Assert\Length(min: 2, max: 50)]
    public string $nombre;

    #[Assert\NotBlank(message: "Los apellidos son obligatorios.")]
    public string $apellidos;

    #[Assert\Regex(pattern: "/^[0-9+ ]+$/", message: "El teléfono solo puede contener números.")]
    public ?string $telefono = null;

    // Validamos que sea una fecha válida
    #[Assert\Date(message: "El formato debe ser YYYY-MM-DD.")]
    public ?string $fechaNac = null;

    // Descripción personal del voluntario
    #[Assert\Length(max: 500, maxMessage: "La descripción no puede tener más de 500 caracteres")]
    public ?string $descripcion = null;

    // Preferencias opcionales
    public ?array $preferencias_ids = null;
}
