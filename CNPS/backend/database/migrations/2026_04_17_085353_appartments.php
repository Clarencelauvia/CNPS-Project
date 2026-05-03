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
        schema::create('appartments', function (Blueprint $table){
            $table->id();
            $table->foreignId('building_id')->constrained('buildings')->cascadeOnDelete();
            $table->string('appartment_number');
            $table->integer('floor')->nullable();
            $table->integer('rooms')->default(1);
            $table->integer('bathrooms')->default(1);
            $table->decimal('surface_area', 8, 2)->nullable();
            $table->decimal('rent_amount', 12, 0);
            $table->boolean('is_occupied')->default(false);
            $table->boolean('is_furnished')->default(false);
            $table->boolean('has_parking')->default(false);
            $table->enum('status', ['available', 'occupied', 'maintenance', 'reserved'])->default('available');
            $table->text('description')->nullable();
            $table->foreignId('current_tenant_id')->nullable()->constrained('users')->onDelete('set null');
            $table->json('images')->nullable();
            $table->string('video_url')->nullable();
            $table->timestamps();

            $table->unique(['building_id', 'appartment_number']);

            $table->index('current_tenant_id');
            $table->index('is_occupied');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        schema::dropIfExists('appartments');
    }
};
