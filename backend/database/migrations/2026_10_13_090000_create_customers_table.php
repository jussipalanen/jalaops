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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Finnish business ID (y-tunnus), e.g. 1234567-8.
            $table->string('business_id', 9)->nullable();
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('billing_street_address')->nullable();
            $table->string('billing_postal_code', 10)->nullable();
            $table->string('billing_city', 100)->nullable();
            // Days from the invoice date to the due date.
            $table->unsignedSmallInteger('payment_terms_days')->default(14);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('service_requests', function (Blueprint $table) {
            // A customer with requests can't be deleted (see CustomerController).
            $table->foreignId('customer_id')->nullable()->after('id')->constrained()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });

        Schema::dropIfExists('customers');
    }
};
