<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->string('id_document_path')->nullable()->after('nationality');
            $table->string('vip_tier')->default('none')->after('id_document_path');
            $table->boolean('is_blacklisted')->default(false)->after('vip_tier');
            $table->text('blacklist_reason')->nullable()->after('is_blacklisted');

            $table->index('vip_tier');
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropIndex(['vip_tier']);
            $table->dropColumn(['id_document_path', 'vip_tier', 'is_blacklisted', 'blacklist_reason']);
        });
    }
};
