<?php

namespace App\Filament\Pages;

use App\Models\RepairFeedback;
use Filament\Pages\Page;

class RepairDashboard extends Page
{
    protected static ?string $navigationGroup = 'Dashboard';

    protected static ?string $navigationLabel = 'Repair Dashboard';

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'repair-dashboard';

    protected static string $view = 'filament.pages.repair-dashboard';

    protected function getViewData(): array
    {
        $count = RepairFeedback::query()->count();
        $avg = round((float) RepairFeedback::query()->avg('rating'), 2);

        return ['feedbackCount' => $count, 'feedbackAvg' => $avg];
    }
}
