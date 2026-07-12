<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('purchase_order_number', 64)->nullable()->after('sales_channel');
            $table->index(['user_id', 'purchase_order_number']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'purchase_order_number']);
            $table->dropColumn('purchase_order_number');
        });
    }
};
