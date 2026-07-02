<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->json('attribute_changes')->nullable()->after('properties');
        });

        $this->forEachRow(function (object $row): void {
            $properties = json_decode($row->properties ?? '', true);

            if (! is_array($properties)) {
                return;
            }

            $changes = array_intersect_key($properties, array_flip(['attributes', 'old']));
            $remaining = array_diff_key($properties, array_flip(['attributes', 'old']));

            DB::table('activity_log')->where('id', $row->id)->update([
                'attribute_changes' => empty($changes) ? null : json_encode($changes),
                'properties' => empty($remaining) ? null : json_encode($remaining),
            ]);
        });

        if (Schema::hasColumn('activity_log', 'batch_uuid')) {
            Schema::table('activity_log', function (Blueprint $table): void {
                $table->dropColumn('batch_uuid');
            });
        }
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->uuid('batch_uuid')->nullable()->after('properties');
        });

        $this->forEachRow(function (object $row): void {
            $changes = json_decode($row->attribute_changes ?? '', true);

            if (! is_array($changes) || $changes === []) {
                return;
            }

            $properties = json_decode($row->properties ?? '', true);
            $properties = is_array($properties) ? $properties : [];

            DB::table('activity_log')->where('id', $row->id)->update([
                'properties' => json_encode(array_merge($properties, $changes)),
            ]);
        });

        Schema::table('activity_log', function (Blueprint $table): void {
            $table->dropColumn('attribute_changes');
        });
    }

    private function forEachRow(callable $callback): void
    {
        DB::table('activity_log')
            ->whereNotNull('properties')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($callback): void {
                foreach ($rows as $row) {
                    $callback($row);
                }
            });
    }
};
