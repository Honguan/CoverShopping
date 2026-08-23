<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $seen = [];

        foreach (DB::table('cart_items')->orderBy('id')->lazyById() as $item) {
            if (($item->user_id === null) === ($item->session_id === null)) {
                DB::table('cart_items')->where('id', $item->id)->delete();

                continue;
            }

            $scope = $item->user_id === null ? 's:'.$item->session_id : 'u:'.$item->user_id;
            $key = $scope.':'.$item->product_id.':'.($item->product_variant_id ?? 0);

            if (! isset($seen[$key])) {
                $seen[$key] = $item->id;

                continue;
            }

            DB::table('cart_items')->where('id', $seen[$key])->increment('quantity', $item->quantity);
            DB::table('cart_items')->where('id', $item->id)->delete();
        }

        Schema::table('cart_items', function (Blueprint $table) {
            $table->index('user_id', 'cart_items_user_id_index');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_user_product_variant_unique');
            $table->dropUnique('cart_session_product_variant_unique');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE cart_items ADD COLUMN scope_key VARCHAR(130) GENERATED ALWAYS AS (CASE WHEN user_id IS NOT NULL THEN CONCAT('u:', user_id) ELSE CONCAT('s:', session_id) END) VIRTUAL");
            DB::statement('ALTER TABLE cart_items ADD COLUMN variant_key BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(product_variant_id, 0)) VIRTUAL');
            DB::statement('ALTER TABLE cart_items ADD CONSTRAINT cart_owner_xor CHECK ((user_id IS NULL) <> (session_id IS NULL))');
        } else {
            DB::statement("ALTER TABLE cart_items ADD COLUMN scope_key TEXT GENERATED ALWAYS AS (CASE WHEN user_id IS NOT NULL THEN 'u:' || user_id ELSE 's:' || session_id END) VIRTUAL");
            DB::statement('ALTER TABLE cart_items ADD COLUMN variant_key INTEGER GENERATED ALWAYS AS (COALESCE(product_variant_id, 0)) VIRTUAL');
            DB::statement("CREATE TRIGGER cart_owner_xor_insert BEFORE INSERT ON cart_items WHEN ((NEW.user_id IS NULL) = (NEW.session_id IS NULL)) BEGIN SELECT RAISE(ABORT, 'cart owner must be exactly one scope'); END");
            DB::statement("CREATE TRIGGER cart_owner_xor_update BEFORE UPDATE OF user_id, session_id ON cart_items WHEN ((NEW.user_id IS NULL) = (NEW.session_id IS NULL)) BEGIN SELECT RAISE(ABORT, 'cart owner must be exactly one scope'); END");
        }

        Schema::table('cart_items', function (Blueprint $table) {
            $table->unique(['scope_key', 'product_id', 'variant_key'], 'cart_identity_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('cart_identity_unique');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE cart_items DROP CHECK cart_owner_xor');
        } else {
            DB::statement('DROP TRIGGER IF EXISTS cart_owner_xor_insert');
            DB::statement('DROP TRIGGER IF EXISTS cart_owner_xor_update');
        }

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn(['scope_key', 'variant_key']);
            $table->unique(['user_id', 'product_id', 'product_variant_id'], 'cart_user_product_variant_unique');
            $table->unique(['session_id', 'product_id', 'product_variant_id'], 'cart_session_product_variant_unique');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropIndex('cart_items_user_id_index');
        });
    }
};
