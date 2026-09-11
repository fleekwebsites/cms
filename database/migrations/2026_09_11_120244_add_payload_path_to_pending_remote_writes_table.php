<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_remote_writes', function (Blueprint $table) {
            $table->string('payload_path')->nullable()->after('payload');
        });
    }

    public function down(): void
    {
        Schema::table('pending_remote_writes', function (Blueprint $table) {
            $table->dropColumn('payload_path');
        });
    }
};
