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
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->char('request_hash', 64);
            $table->string('status', 20);
            $table->bigInteger('total_cents')->nullable();
            $table->string('card_brand', 20);
            $table->char('card_last4', 4);
            $table->string('charge_reference', 64)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'id']);
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('sku', 64);
            $table->string('name', 255);
            $table->integer('unit_price_cents');
            $table->integer('quantity');
            $table->bigInteger('line_total_cents');
        });

        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_valid CHECK (status IN ('pending', 'paid'))");
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_total_non_negative CHECK (total_cents >= 0)');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_paid_is_complete CHECK (status <> 'paid' OR (total_cents IS NOT NULL AND charge_reference IS NOT NULL))");
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_quantity_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_unit_price_non_negative CHECK (unit_price_cents >= 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_line_total_matches CHECK (line_total_cents = unit_price_cents::bigint * quantity)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
