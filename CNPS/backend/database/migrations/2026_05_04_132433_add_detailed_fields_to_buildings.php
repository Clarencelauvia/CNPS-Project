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
        Schema::table('buildings', function (Blueprint $table) {
             $table->integer('total_floors')->default(1)->after('city');
            $table->integer('total_parking_spots')->default(0)->after('total_floors');
            $table->integer('available_parking_spots')->default(0)->after('total_parking_spots');
            $table->json('floor_configuration')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
        $table->dropColumn(['total_floors', 'total_parking_spots', 'available_parking_spots', 'floor_configuration']);
        });
    }
};
