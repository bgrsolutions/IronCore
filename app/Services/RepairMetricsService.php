<?php

namespace App\Services;

use App\Models\Repair;
use Illuminate\Support\Facades\DB;

final class RepairMetricsService
{
    public function loggedMinutes(Repair $repair): int
    {
        return (int) DB::table('repair_time_entries')->where('repair_id', $repair->id)->sum('minutes');
    }

    public function hasLabourLines(Repair $repair): bool
    {
        return DB::table('repair_line_items')
            ->where('repair_id', $repair->id)
            ->where(function ($query): void {
                $query->where('line_type', 'labour')
                    ->orWhereRaw('LOWER(COALESCE(description, "")) LIKE ?', ['%labour%'])
                    ->orWhereRaw('LOWER(COALESCE(description, "")) LIKE ?', ['%mano de obra%']);
            })
            ->exists();
    }

    public function hasTimeLeak(Repair $repair): bool
    {
        $threshold = (int) config('repairs.time_leak_threshold_minutes', 15);

        return $this->loggedMinutes($repair) > $threshold && ! $this->hasLabourLines($repair);
    }

    public function addQuickLabourLine(Repair $repair, int $minutes): void
    {
        $hours = round($minutes / 60, 2);
        $hourRate = (float) config('repairs.labour_rate_per_hour_net', 60.00);
        $taxRate = (float) config('repairs.default_tax_rate', 7.0);
        $lineNet = round($hours * $hourRate, 2);

        DB::table('repair_line_items')->insert([
            'company_id' => $repair->company_id,
            'repair_id' => $repair->id,
            'line_type' => 'labour',
            'description' => sprintf('Labour (%d minutes)', $minutes),
            'qty' => $hours,
            'unit_price' => $hourRate,
            'tax_rate' => $taxRate,
            'line_net' => $lineNet,
            'cost_total' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
