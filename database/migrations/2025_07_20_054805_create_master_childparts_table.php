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
        Schema::create('master_childparts', function (Blueprint $table) {
            $table->id();
            $table->string('part_number')->unique();
            $table->index('part_number');
            $table->string('part_name')->nullable();
            $table->string('model')->nullable();
            $table->string('variant')->nullable();
            $table->enum('type', ['draft', 'small', 'medium', 'big'])->default('draft');
            $table->integer('stock')->default(0);
            $table->string('homeline')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_childparts');
    }
};
