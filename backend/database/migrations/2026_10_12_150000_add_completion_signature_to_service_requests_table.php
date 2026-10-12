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
            // The signature drawn when the request is completed, as a PNG data URL.
            $table->longText('completion_signature')->nullable()->after('longitude');
            $table->string('signed_by', 100)->nullable()->after('completion_signature');
            $table->timestamp('signed_at')->nullable()->after('signed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropColumn(['completion_signature', 'signed_by', 'signed_at']);
        });
    }
};
