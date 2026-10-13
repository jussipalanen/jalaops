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
        // Work and materials on a request, the basis for invoicing.
        Schema::create('request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            // work or material (App\Enums\RequestItemType)
            $table->string('type', 20);
            $table->string('description');
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 20);
            // Money as whole cents and VAT rates as basis points (25.5 % = 2550),
            // so sums never drift by fractions of a cent.
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedSmallInteger('vat_rate_basis_points');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_items');
    }
};
