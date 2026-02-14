<?php

declare(strict_types=1);

namespace Skywalker\Entrust;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CleanupCommand extends Command
{
    protected $signature = 'skywalker:audit-cleanup {--days=30 : Number of days to retain logs}';
    protected $description = 'Cleanup old audit logs from the database';

    public function handle(): void
    {
        $days = (int) $this->option('days');
        $date = Carbon::now()->subDays($days);

        $this->info("Cleaning up audit logs older than {$days} days ({$date->toDateString()})...");

        $count = DB::table('entrust_audit_logs')
            ->where('created_at', '<', $date)
            ->delete();

        $this->info("Successfully deleted {$count} audit log entries.");
    }
}
