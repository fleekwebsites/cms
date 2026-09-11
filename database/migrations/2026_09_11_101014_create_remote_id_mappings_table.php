<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remote_id_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('resource');
            $table->unsignedInteger('client_id');
            $table->unsignedInteger('remote_id');
            $table->timestamps();

            $table->unique(['site_id', 'resource', 'client_id']);
            $table->index(['site_id', 'resource', 'remote_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remote_id_mappings');
    }
};
