<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->foreignId('site_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        $defaultSiteId = DB::table('sites')->orderBy('id')->value('id');

        if ($defaultSiteId !== null) {
            DB::table('authors')->whereNull('site_id')->update(['site_id' => $defaultSiteId]);
        }

        Schema::table('authors', function (Blueprint $table) {
            $table->unique(['site_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            $table->dropUnique(['site_id', 'name']);
            $table->dropConstrainedForeignId('site_id');
            $table->unique('name');
        });
    }
};
