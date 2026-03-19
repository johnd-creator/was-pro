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
            $table->uuid('organization_id')->nullable()->after('id');
            $table->uuid('role_id')->nullable()->after('organization_id');
            $table->boolean('is_super_admin')->default(false)->after('role_id');
            $table->uuid('created_by')->nullable()->after('updated_at');
            $table->uuid('updated_by')->nullable()->after('created_by');

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('set null');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('set null');

            $table->index('organization_id');
            $table->index('role_id');
            $table->index('is_super_admin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['role_id']);
            $table->dropIndex(['organization_id']);
            $table->dropIndex(['role_id']);
            $table->dropIndex(['is_super_admin']);
            $table->dropColumn(['organization_id', 'role_id', 'is_super_admin', 'created_by', 'updated_by']);
        });
    }
};
