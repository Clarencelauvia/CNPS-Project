<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_spots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->onDelete('cascade');
            $table->string('spot_number');
            $table->enum('type', ['covered', 'open', 'basement'])->default('open');
            $table->boolean('is_occupied')->default(false);
            $table->foreignId('current_tenant_id')->nullable()->constrained('users')->onDelete('set null');
            $table->decimal('monthly_price', 12, 0)->default(0);
            $table->enum('status', ['available', 'occupied', 'reserved', 'maintenance'])->default('available');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_spots');
    }
};