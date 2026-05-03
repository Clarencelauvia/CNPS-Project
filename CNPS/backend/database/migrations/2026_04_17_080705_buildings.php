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
        schema::create('buildings', function (Blueprint $table){
            $table->id();
            $table->string('name');
            $table->string('region');
            $table->string('city');
            $table->string('address');
            $table->boolean('is_furnished')->default(false);
            $table->boolean('has_parking')->default(false);
            $table->integer('total_appartments')->default(0);
            $table->integer('available_appartments')->default(0);
            $table->decimal('rent_price', 10, 2)->default(0.00);
            $table->text('description')->nullable();
            $table->json('images')->nullable();
            $table->string('video_url')->nullable();
            $table->enum('status', ['active', 'maintenance', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        schema::dropIfExists('buildings');
    }
};
