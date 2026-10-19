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
        // Where a module's widget is shown, set with modules:place: a hook
        // from config/modules.php, or "hidden". Without a row, the widget's
        // module.json decides.
        Schema::create('module_widget_settings', function (Blueprint $table) {
            $table->string('widget', 130)->primary();
            $table->string('hook', 64);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_widget_settings');
    }
};
