<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('auctions', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::dropIfExists('categories');
    }
};
