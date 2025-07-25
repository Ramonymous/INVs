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
        Schema::create('inv_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('inv_requests')->cascadeOnDelete();
            $table->foreignId('child_part_id')->constrained('master_childparts')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1); // Tiap scan +1
            $table->boolean('fulfilled')->default(false); // Sudah dikeluarkan atau belum
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_request_items');
    }
};
