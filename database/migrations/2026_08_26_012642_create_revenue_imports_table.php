<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('period', 7);
            $table->string('source_file')->nullable();
            $table->decimal('gross_total', 15, 2)->default(0);
            $table->decimal('net_total', 15, 2)->default(0);
            $table->string('status')->default('imported');
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['hotel_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_imports');
    }
};
