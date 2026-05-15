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
        Schema::table('rental_requests', function (Blueprint $table) {
                        if (!Schema::hasColumn('rental_requests', 'building_id')) {
                $table->foreignId('building_id')->after('apartment_id')->constrained('buildings')->onDelete('cascade');
            }
            if (!Schema::hasColumn('rental_requests', 'start_date')) {
                $table->date('start_date')->nullable()->after('building_id');
            }
            if (!Schema::hasColumn('rental_requests', 'duration')) {
                $table->integer('duration')->default(12)->after('start_date');
            }
            if (!Schema::hasColumn('rental_requests', 'document_ids')) {
                $table->json('document_ids')->nullable()->after('message');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rental_requests', function (Blueprint $table) {
        $table->dropColumn(['building_id', 'start_date', 'duration', 'document_ids']);
        });
    }
};
