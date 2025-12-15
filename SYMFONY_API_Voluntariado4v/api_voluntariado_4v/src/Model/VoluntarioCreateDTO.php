<?php
// DTO para la creación de un voluntario: Lo que te manda el frontend
namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

class VoluntarioCreateDTO
{
    public function __construct(
        #[Assert\NotBlank(message: "El Google ID es obligatorio")]
        public string $google_id,

        #[Assert\NotBlank]
        #[Assert\Email]
        public string $correo,

        #[Assert\NotBlank]
        #[Assert\Length(min: 2)]
        public string $nombre,

        #[Assert\NotBlank]
        public string $apellidos,

        #[Assert\NotBlank]
        // Aquí podrías meter una Regex para DNI/NIE si quisieras ser estricto
        public string $dni,

        #[Assert\NotBlank]
        public string $telefono,

        #[Assert\NotBlank]
        #[Assert\Date]
        public string $fecha_nac,

        public bool $carnet_conducir = false,

        #[Assert\NotBlank]
        public int $id_curso_actual,

        // Arrays de IDs para relaciones (más simple que pasar objetos enteros)
        public array $preferencias_ids = [],
        
        // Array de objetos simples para idiomas: [['id' => 1, 'nivel' => 'B2'], ...]
        public array $idiomas = [] 
    ) {}
}