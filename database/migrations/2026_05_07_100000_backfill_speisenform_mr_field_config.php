<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Platform\Events\Models\MrFieldConfig;

return new class extends Migration
{
    public function up(): void
    {
        $teamIds = DB::table('events_mr_field_configs')
            ->whereNull('deleted_at')
            ->whereNotNull('team_id')
            ->distinct()
            ->pluck('team_id');

        foreach ($teamIds as $teamId) {
            $exists = MrFieldConfig::where('team_id', $teamId)
                ->where('label', 'Speisenform')
                ->exists();
            if ($exists) {
                continue;
            }

            $maxSort = (int) MrFieldConfig::where('team_id', $teamId)->max('sort_order');

            MrFieldConfig::create([
                'team_id'     => $teamId,
                'group_label' => 'Produktion',
                'label'       => 'Speisenform',
                'options'     => [],
                'sort_order'  => $maxSort + 1,
                'is_active'   => true,
            ]);
        }
    }

    public function down(): void
    {
        MrFieldConfig::where('label', 'Speisenform')->forceDelete();
    }
};
