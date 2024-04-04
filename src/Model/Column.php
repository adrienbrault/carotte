<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints\NotBlank;

class Column
{
    public function __construct(
        #[NotBlank()]
        public ?string $name = null,
        public ?string $description = null,
        public ColumnType $type = ColumnType::TEXT,
    ) {
    }
}
