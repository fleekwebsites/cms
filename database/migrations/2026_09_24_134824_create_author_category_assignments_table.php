<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('author_category_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('author_id');
            $table->unsignedBigInteger('category_id');
            $table->timestamps();

            $table->unique(['site_id', 'author_id', 'category_id']);
            $table->index(['site_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('author_category_assignments');
    }
};
