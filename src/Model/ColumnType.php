<?php

namespace App\Model;

enum ColumnType: string
{
    case TEXT = 'text';
    case NUMBER = 'number';
    case INTEGER = 'integer';
    case DATE = 'date';
    case BOOLEAN = 'boolean';
}