<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->index(['is_active', 'name']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['status', 'created_at']);
            $table->index(['status', 'price']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index(['seller_id', 'shipping_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'name']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['status', 'price']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['seller_id', 'shipping_status', 'created_at']);
        });
    }
};
