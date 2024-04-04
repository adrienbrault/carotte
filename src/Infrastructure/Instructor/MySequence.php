<?php

namespace App\Infrastructure\Instructor;

use App\Entity\Spreadsheet;
use App\Model\Column;
use Cognesy\Instructor\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Extras\Sequences\Sequence;
use Cognesy\Instructor\Schema\Data\Schema\ArraySchema;
use Cognesy\Instructor\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\Data\Schema\ScalarSchema;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Schema\Data\TypeDetails;
use function Psl\Dict\map;

class MySequence implements CanProvideSchema, CanDeserializeSelf
{
    public function __construct(
        public readonly Spreadsheet $spreadsheet,
        public ?array $list = null
    ) {
    }

    /**
     * @param list<Column> $columns
     */
    public static function createSchema(array $columns): ObjectSchema
    {
        $objectType = new TypeDetails('object');
        $nestedSchema = new ObjectSchema(
            $objectType,
            '',
            '',
            map(
                $columns,
                fn($column) => new ScalarSchema(
                    new TypeDetails('string'),
                    $column->name,
                    $column->description
                )
            ),
            map(
                $columns,
                fn($column) => $column->name
            ),
        );

        $arrayTypeDetails = new TypeDetails(
            type: 'array',
            class: null,
            nestedType: $objectType,
            enumType: null,
            enumValues: null,
        );
        $arraySchema = new ArraySchema(
            type: $arrayTypeDetails,
            name: 'list',
            description: '',
            nestedItemSchema: $nestedSchema,
        );
        $objectSchema = new ObjectSchema(
            type: new TypeDetails(
                type: 'object',
                class: Sequence::class,
                nestedType: null,
                enumType: null,
                enumValues: null,
            )
        );
        $objectSchema->properties['list'] = $arraySchema;
        $objectSchema->required = ['list'];

        return $objectSchema;
    }

    public function toSchema(): Schema {

        return self::createSchema($this->spreadsheet->columns);
    }

    public function fromJson(string $jsonData): static
    {
        $data = json_decode($jsonData, true, flags: JSON_PARTIAL_OUTPUT_ON_ERROR);

        return new self(
            $this->spreadsheet,
            $data['list']
        );
    }
}
