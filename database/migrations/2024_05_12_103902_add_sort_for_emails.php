<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->integer('sort')->default(0)->after('cc');
        });
    }

    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropColumn('sort');
        });
    }
};
