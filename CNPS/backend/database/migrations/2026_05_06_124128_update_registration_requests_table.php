<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add type column to user_registration_requests
        Schema::table('user_registration_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('user_registration_requests', 'type')) {
                $table->enum('type', ['account_creation', 'rental_request'])->default('account_creation')->after('user_id');
            }
        });

        // Add rental_apartment_id to track which apartment user wants to rent
        Schema::table('user_registration_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('user_registration_requests', 'rental_apartment_id')) {
                $table->foreignId('rental_apartment_id')->nullable()->constrained('appartments')->nullOnDelete()->after('type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_registration_requests', function (Blueprint $table) {
            $table->dropColumn(['type', 'rental_apartment_id']);
        });
    }
};