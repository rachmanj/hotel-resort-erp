<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dive_rate_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 200);
            $table->string('item_type', 40);
            $table->string('route', 100)->nullable();
            $table->text('dive_spots')->nullable();
            $table->string('boat_engine_option', 30)->nullable();
            $table->decimal('price', 14, 2);
            $table->unsignedInteger('min_pax')->nullable();
            $table->date('valid_from');
            $table->date('valid_to');
            $table->timestamps();

            $table->index(['item_type', 'route']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dive_rate_items');
    }
};
