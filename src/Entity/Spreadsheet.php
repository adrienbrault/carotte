<?php

namespace App\Entity;

use App\Model\Column;

class Spreadsheet
{
    /**
     * @param array<Column> $columns
     */
    public function __construct(
        public array $columns = [],
        public string $context = '',
        public bool $autoExtract = false,
    ) {
    }
}
