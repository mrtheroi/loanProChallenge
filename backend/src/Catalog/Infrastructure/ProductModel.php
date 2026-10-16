<?php

declare(strict_types=1);

namespace Ecommerce\Catalog\Infrastructure;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $sku
 * @property string $name
 * @property string|null $description
 * @property int $category_id
 * @property int $price_cents
 * @property int $stock
 * @property int|null $weight_grams
 * @property-read CategoryModel $category
 */
final class ProductModel extends Model
{
    use SoftDeletes;

    protected $table = 'products';

    protected $fillable = ['sku', 'name', 'description', 'category_id', 'price_cents', 'stock', 'weight_grams'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'stock' => 'integer',
            'weight_grams' => 'integer',
        ];
    }

    /** @return BelongsTo<CategoryModel, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CategoryModel::class, 'category_id');
    }
}
