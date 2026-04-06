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
       Schema::table('users', function (Blueprint $table) {
            // Check if column doesn't exist before adding
               if (!Schema::hasColumn('users', 'telephone')) {
                $table->string('telephone')->nullable()->after('email');
            }
               // Add id_number after telephone (not 'phone')
            if (!Schema::hasColumn('users', 'id_number')) {
                $table->string('id_number')->nullable()->after('telephone');
            }
           // Add user_type if it doesn't exist
            if (!Schema::hasColumn('users', 'user_type')) {
                $table->enum('user_type', ['morale', 'salarie', 'non_salarie'])->nullable()->after('password');
            }
            
            // Add approval_status if it doesn't exist
            if (!Schema::hasColumn('users', 'approval_status')) {
                $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending')->after('user_type');
            }
              // Add address if it doesn't exist
            if (!Schema::hasColumn('users', 'address')) {
                $table->string('address')->nullable()->after('telephone');
            }
            
            // Add rejection_reason if it doesn't exist
            if (!Schema::hasColumn('users', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('approval_status');
            }

             // Add approved_at if it doesn't exist
            if (!Schema::hasColumn('users', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('rejection_reason');
            }
            
            // Add approved_by if it doesn't exist
            if (!Schema::hasColumn('users', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->constrained('admins')->after('approved_at');
            }

        
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'telephone', 
                'id_number', 
                'user_type', 
                'approval_status', 
                'address',
                'rejection_reason', 
                'approved_at', 
                'approved_by'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
