<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presence_heartbeats', function (Blueprint $table) {
            $table->string('path', 500)->nullable()->after('page_type');
        });
    }

    public function down(): void
    {
        Schema::table('presence_heartbeats', function (Blueprint $table) {
            $table->dropColumn('path');
        });
    }
};
