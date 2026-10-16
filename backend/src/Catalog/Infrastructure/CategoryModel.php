<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Infrastructure;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $normalized_name
 */
final class CategoryModel extends Model
{
    protected $table = 'categories';

    protected $fillable = ['name', 'normalized_name'];
}
