<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function normalizeTags(array $tokens): array
    {
        $result = [];
        $seen = [];

        foreach ($tokens as $token) {
            if (!is_string($token)) {
                continue;
            }

            $parts = preg_split('/[,\s]+/', trim($token)) ?: [];
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }

                $tag = str_starts_with($part, '#') ? $part : ('#' . $part);
                $key = mb_strtolower($tag);
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $result[] = $tag;
                }
            }
        }

        return $result;
    }

    public function up(): void
    {
        if (!Schema::hasColumn('educational_modules', 'vital_tags_json')) {
            Schema::table('educational_modules', function (Blueprint $table) {
                $table->json('vital_tags_json')->nullable()->after('difficulty');
            });
        }

        $modules = DB::table('educational_modules')
            ->select('id', 'vital_tags_json', 'watering_info', 'light_info', 'humidity_info', 'difficulty')
            ->get();

        foreach ($modules as $module) {
            $existingTags = [];
            if (!empty($module->vital_tags_json)) {
                $decoded = json_decode($module->vital_tags_json, true);
                if (is_array($decoded)) {
                    $existingTags = $decoded;
                }
            }

            $merged = $this->normalizeTags(array_merge($existingTags, [
                (string) ($module->watering_info ?? ''),
                (string) ($module->light_info ?? ''),
                (string) ($module->humidity_info ?? ''),
                (string) ($module->difficulty ?? ''),
            ]));

            DB::table('educational_modules')
                ->where('id', $module->id)
                ->update([
                    'vital_tags_json' => !empty($merged) ? json_encode($merged) : null,
                    'watering_info' => null,
                    'light_info' => null,
                    'humidity_info' => null,
                    'difficulty' => null,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('educational_modules', 'vital_tags_json')) {
            Schema::table('educational_modules', function (Blueprint $table) {
                $table->dropColumn('vital_tags_json');
            });
        }
    }
};

