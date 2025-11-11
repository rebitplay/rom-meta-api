<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('region')->nullable();
            $table->unsignedSmallInteger('release_year')->nullable()->index();
            $table->string('crc', 8)->nullable()->index();
            $table->string('md5', 32)->nullable()->index();
            $table->string('sha1', 40)->nullable()->index();
            $table->string('serial', 50)->nullable()->index();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('filename')->nullable();
            $table->timestamps();

            // Composite indexes for common queries
            $table->index(['system_id', 'crc']);
            $table->index(['system_id', 'serial']);
            $table->index(['system_id', 'release_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
