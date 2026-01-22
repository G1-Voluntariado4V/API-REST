<?php

namespace App\Model\Ods;

class OdsDTO
{
    public function __construct(
        public int $id,
        public ?string $nombre = null,
        public ?string $img_ods = null,
        public ?string $img_url = null
    ) {}
}
