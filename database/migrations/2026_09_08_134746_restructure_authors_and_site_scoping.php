<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['site_id', 'name']);
        });

        Schema::table('author_names', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id', 'name']);
            $table->dropColumn('user_id');
            $table->string('credentials')->nullable()->after('name');
            $table->text('bio')->nullable()->after('credentials');
        });

        $duplicateNames = DB::table('author_names')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name');

        foreach ($duplicateNames as $name) {
            $ids = DB::table('author_names')
                ->where('name', $name)
                ->orderBy('id')
                ->pluck('id');

            foreach ($ids->slice(1) as $id) {
                DB::table('author_names')
                    ->where('id', $id)
                    ->update(['name' => $name.' #'.$id]);
            }
        }

        Schema::rename('author_names', 'authors');

        Schema::table('authors', function (Blueprint $table) {
            $table->unique('name');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->dropForeign(['author_name_id']);
            $table->dropColumn(['category', 'author_credentials', 'author_bio']);
            $table->foreignId('site_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->foreignId('site_category_id')->nullable()->after('site_id')->constrained()->nullOnDelete();
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->renameColumn('author_name_id', 'author_id');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->foreign('author_id')->references('id')->on('authors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropForeign(['author_id']);
            $table->dropForeign(['site_id']);
            $table->dropForeign(['site_category_id']);
            $table->dropColumn(['site_id', 'site_category_id']);
            $table->string('category')->nullable();
            $table->string('author_credentials')->nullable();
            $table->text('author_bio')->nullable();
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->renameColumn('author_id', 'author_name_id');
        });

        Schema::table('authors', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });

        Schema::rename('authors', 'author_names');

        Schema::table('author_names', function (Blueprint $table) {
            $table->dropColumn(['credentials', 'bio']);
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unique(['user_id', 'name']);
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->foreign('author_name_id')->references('id')->on('author_names')->nullOnDelete();
        });

        Schema::dropIfExists('site_categories');
    }
};
