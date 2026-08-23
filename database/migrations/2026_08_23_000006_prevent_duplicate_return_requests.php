<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->timestamp('inventory_restocked_at')->nullable()->after('status');
            $table->unique('order_id', 'return_requests_order_unique');
        });
    }

    public function down(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->dropUnique('return_requests_order_unique');
            $table->dropColumn('inventory_restocked_at');
        });
    }
};
