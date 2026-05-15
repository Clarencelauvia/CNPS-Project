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
        Schema::table('appartments', function (Blueprint $table) {
            $table->integer('floor_number')->default(1)->after('appartment_number');
            $table->enum('furnishing_status', ['furnished', 'unfurnished', 'semi_furnished'])->default('unfurnished')->after('is_furnished');
            $table->decimal('furnished_rent_price', 12, 0)->nullable()->after('rent_amount');
            $table->decimal('unfurnished_rent_price', 12, 0)->nullable()->after('furnished_rent_price');
            $table->boolean('has_balcony')->default(false)->after('has_parking');
            $table->boolean('has_air_conditioning')->default(false)->after('has_balcony');
            $table->boolean('has_water_heater')->default(false)->after('has_air_conditioning');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appartments', function (Blueprint $table) {
              $table->dropColumn([
                'floor_number', 'furnishing_status', 'furnished_rent_price',
                'unfurnished_rent_price', 'has_balcony', 'has_air_conditioning',
                'has_water_heater'
            ]);
        });
    }
};
