<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bike_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bike_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();
        });

        // Carry over any existing single bike photo into the new gallery.
        DB::table('bikes')->whereNotNull('photo_path')->get(['id', 'photo_path'])->each(function ($bike) {
            DB::table('bike_photos')->insert([
                'bike_id' => $bike->id,
                'path' => $bike->photo_path,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Schema::table('bikes', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bikes', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('engine_cc');
        });

        Schema::dropIfExists('bike_photos');
    }
};
