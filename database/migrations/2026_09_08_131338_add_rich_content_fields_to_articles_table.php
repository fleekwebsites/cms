<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('category')->nullable()->after('keywords');
            $table->string('author_credentials')->nullable()->after('author_name_id');
            $table->text('author_bio')->nullable()->after('author_credentials');
            $table->unsignedSmallInteger('reading_time_minutes')->nullable()->after('author_bio');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'author_credentials',
                'author_bio',
                'reading_time_minutes',
            ]);
        });
    }
};
