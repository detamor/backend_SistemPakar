<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('educational_modules')) {
            return;
        }

        Schema::table('educational_modules', function (Blueprint $table) {
            if (! Schema::hasColumn('educational_modules', 'plant_id')) {
                $table->foreignId('plant_id')->nullable()->after('content')->constrained('plants')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('educational_modules', 'category')) {
            $plants = DB::table('plants')->select('id', 'name')->get();
            $plantMap = [];
            foreach ($plants as $plant) {
                if (is_string($plant->name) && $plant->name !== '') {
                    $plantMap[mb_strtolower($plant->name)] = (int) $plant->id;
                }
            }

            DB::table('educational_modules')
                ->select('id', 'category')
                ->whereNotNull('category')
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($plantMap) {
                    foreach ($rows as $row) {
                        $category = is_string($row->category) ? trim($row->category) : '';
                        if ($category === '') {
                            continue;
                        }
                        $key = mb_strtolower($category);
                        if (!isset($plantMap[$key])) {
                            continue;
                        }
                        DB::table('educational_modules')
                            ->where('id', (int) $row->id)
                            ->update(['plant_id' => $plantMap[$key]]);
                    }
                });

            Schema::table('educational_modules', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('educational_modules')) {
            return;
        }

        if (! Schema::hasColumn('educational_modules', 'category')) {
            Schema::table('educational_modules', function (Blueprint $table) {
                $table->string('category')->nullable()->after('content');
            });
        }

        if (Schema::hasColumn('educational_modules', 'plant_id') && Schema::hasColumn('educational_modules', 'category')) {
            $plants = DB::table('plants')->select('id', 'name')->get();
            $plantNames = [];
            foreach ($plants as $plant) {
                $plantNames[(int) $plant->id] = $plant->name;
            }

            DB::table('educational_modules')
                ->select('id', 'plant_id')
                ->whereNotNull('plant_id')
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($plantNames) {
                    foreach ($rows as $row) {
                        $plantId = (int) $row->plant_id;
                        if (!isset($plantNames[$plantId])) {
                            continue;
                        }
                        DB::table('educational_modules')
                            ->where('id', (int) $row->id)
                            ->update(['category' => $plantNames[$plantId]]);
                    }
                });
        }

        if (Schema::hasColumn('educational_modules', 'plant_id')) {
            Schema::table('educational_modules', function (Blueprint $table) {
                $table->dropConstrainedForeignId('plant_id');
            });
        }
    }
};

