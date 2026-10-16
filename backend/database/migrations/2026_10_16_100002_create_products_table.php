<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('sku', 64)->unique();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained('categories');
            $table->bigInteger('price_cents');
            $table->integer('stock');
            $table->integer('weight_grams')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_cents_non_negative CHECK (price_cents >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_stock_non_negative CHECK (stock >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_weight_grams_non_negative CHECK (weight_grams >= 0)');
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_sku_trimmed_not_blank CHECK (sku <> '' AND sku !~ '^\\s|\\s$')");
        DB::statement("ALTER TABLE products ADD CONSTRAINT products_name_trimmed_not_blank CHECK (name <> '' AND name !~ '^\\s|\\s$')");
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_sku_uppercase CHECK (sku = upper(sku))');

        foreach (['name', 'sku', 'description'] as $column) {
            DB::statement("CREATE INDEX products_{$column}_trgm_index ON products USING gin ({$column} gin_trgm_ops)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
