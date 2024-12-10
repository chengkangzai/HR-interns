<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table((new \App\Models\Position)->getTable(), function (Blueprint $table) {
            $table->string('type')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table((new \App\Models\Position)->getTable(), function (Blueprint $table) {
            $table->string('type');
        });
    }
};
