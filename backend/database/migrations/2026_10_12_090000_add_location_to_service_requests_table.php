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
        Schema::table('service_requests', function (Blueprint $table) {
            $table->string('street_address')->nullable()->after('due_date');
            $table->string('postal_code', 10)->nullable()->after('street_address');
            $table->string('city', 100)->nullable()->after('postal_code');
            // WGS 84 coordinates, as used by OpenStreetMap; 7 decimals is about 1 cm.
            $table->decimal('latitude', 10, 7)->nullable()->after('city');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropColumn(['street_address', 'postal_code', 'city', 'latitude', 'longitude']);
        });
    }
};
