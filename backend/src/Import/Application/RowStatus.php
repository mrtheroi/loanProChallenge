<?php

declare(strict_types=1);

namespace Ecommerce\Import\Application;

enum RowStatus: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Unchanged = 'unchanged';
    case Failed = 'failed';
}
