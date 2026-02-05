<?php

namespace App\Console\Commands;

use App\Models\Classes;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class AppSystemCheck extends Command
{
    protected $signature = 'app:check';

    protected $description = 'Interactive system health and statistics check';

    public function handle(): void
    {
        info('Welcome to QR Absence System Manager');

        $action = select(
            label: 'What would you like to do?',
            options: [
                'stats' => 'View System Statistics',
                'features' => 'Check Feature Flags (Pennant)',
                'cache' => 'Clear Application Cache',
                'db' => 'Check Database Connection',
                'exit' => 'Exit',
            ],
            default: 'stats'
        );

        match ($action) {
            'stats' => $this->showStats(),
            'features' => $this->showFeatures(),
            'cache' => $this->clearAppCache(),
            'db' => $this->checkDatabase(),
            'exit' => info('Goodbye!'),
        };
    }

    private function showFeatures(): void
    {
        $features = [
            ['New Mobile Dashboard', Feature::active('new-mobile-dashboard') ? 'Enabled' : 'Disabled (Admin only)'],
        ];

        table(['Feature', 'Status'], $features);
    }

    private function showStats(): void
    {
        $stats = spin(fn () => [
            ['Users', User::count()],
            ['Teachers', TeacherProfile::count()],
            ['Students', StudentProfile::count()],
            ['Classes', Classes::count()],
        ], 'Fetching statistics...');

        table(['Entity', 'Count'], $stats);

        note('Statistics gathered from production database.');
    }

    private function clearAppCache(): void
    {
        if (confirm('Are you sure you want to clear the application cache?')) {
            spin(function () {
                Cache::flush();
                sleep(1);
            }, 'Clearing cache...');

            info('Cache cleared successfully!');
        }
    }

    private function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
            info('Database connection is healthy.');
            note('Driver: '.DB::connection()->getDriverName());
        } catch (\Exception $e) {
            $this->error('Database connection failed: '.$e->getMessage());
        }
    }
}
