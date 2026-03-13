<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('request_metrics');
    }

    public function down(): void
    {
        // Not recreated — metrics are now handled by Prometheus.
    }
};
