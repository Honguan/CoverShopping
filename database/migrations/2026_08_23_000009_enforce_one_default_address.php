<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateUserIds = DB::table('addresses')
            ->where('is_default', true)
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('user_id');

        foreach ($duplicateUserIds as $userId) {
            $keepId = DB::table('addresses')
                ->where('user_id', $userId)
                ->where('is_default', true)
                ->max('id');

            DB::table('addresses')
                ->where('user_id', $userId)
                ->where('is_default', true)
                ->where('id', '<>', $keepId)
                ->update(['is_default' => false]);
        }

        Schema::table('addresses', function (Blueprint $table): void {
            $table->unsignedTinyInteger('default_marker')
                ->storedAs('CASE WHEN is_default = 1 THEN 1 ELSE NULL END');
            $table->unique(['user_id', 'default_marker'], 'addresses_default_user_unique');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table): void {
            $table->dropUnique('addresses_default_user_unique');
            $table->dropColumn('default_marker');
        });
    }
};
