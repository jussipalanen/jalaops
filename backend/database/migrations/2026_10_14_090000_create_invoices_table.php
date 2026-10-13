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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            // Running invoice number (1001, 1002, …) and its Finnish reference number.
            $table->unsignedInteger('number')->unique();
            $table->string('reference_number', 20);
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            // The customer's billing details when the invoice was made, so later
            // changes to the customer never change an existing invoice.
            $table->string('customer_name');
            $table->string('customer_business_id', 9)->nullable();
            $table->string('billing_street_address')->nullable();
            $table->string('billing_postal_code', 10)->nullable();
            $table->string('billing_city', 100)->nullable();
            $table->date('issue_date');
            $table->date('due_date');
            // draft, sent or paid (App\Enums\InvoiceStatus)
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // The invoiced work and materials, copied from the requests' rows.
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('request_title');
            // When the work was done (the request's sign-off or last update): the
            // delivery date a Finnish invoice must show.
            $table->date('delivered_on')->nullable();
            $table->string('type', 20);
            $table->string('description');
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 20);
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedSmallInteger('vat_rate_basis_points');
            $table->timestamps();
        });

        Schema::table('service_requests', function (Blueprint $table) {
            // The invoice a request is billed on; its rows can't change after that.
            $table->foreignId('invoice_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
        });

        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
