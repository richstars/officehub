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
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('email')->nullable();
            $table->string('department')->nullable();
            $table->string('employee_id')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('bio')->nullable();
        });

        Schema::table('links', function (Blueprint $table) {
            $table->string('icon_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['email', 'department', 'employee_id', 'photo_path', 'bio']);
        });

        Schema::table('links', function (Blueprint $table) {
            $table->dropColumn('icon_path');
        });
    }
};
