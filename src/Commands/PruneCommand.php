<?php

declare(strict_types=1);

namespace Filexus\Commands;

use Filexus\FilexusManager;
use Illuminate\Console\Command;

/**
 * Command to prune expired and orphaned files.
 *
 * Usage:
 *   php artisan filexus:prune
 *   php artisan filexus:prune --expired
 *   php artisan filexus:prune --orphaned
 */
class PruneCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'filexus:prune
                            {--expired : Only prune expired files}
                            {--orphaned : Only prune orphaned files}
                            {--hours-old= : Minimum age in hours for orphaned files (default from config)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune expired and orphaned files from storage';

    /**
     * Execute the console command.
     *
     * @param FilexusManager $manager
     * @return int
     */
    public function handle(FilexusManager $manager): int
    {
        $this->info('Starting file pruning...');
        $this->newLine();

        $expiredOnly = $this->option('expired');
        $orphanedOnly = $this->option('orphaned');
        $hoursOld = $this->option('hours-old') ? (int) $this->option('hours-old') : null;

        $expiredCount = 0;
        $orphanedCount = 0;

        // If specific option is provided, only run that pruner
        if ($expiredOnly) {
            $expiredCount = $this->pruneExpired($manager);
        } elseif ($orphanedOnly) {
            $orphanedCount = $this->pruneOrphaned($manager, $hoursOld);
        } else {
            // Run both pruners
            $expiredCount = $this->pruneExpired($manager);
            $orphanedCount = $this->pruneOrphaned($manager, $hoursOld);
        }

        $totalDeleted = $expiredCount + $orphanedCount;

        $this->newLine();

        if ($totalDeleted > 0) {
            $this->info("✓ Successfully pruned {$totalDeleted} file(s)");
        } else {
            $this->comment('No files to prune.');
        }

        return self::SUCCESS;
    }

    /**
     * Prune expired files.
     *
     * @param FilexusManager $manager
     * @return int
     */
    protected function pruneExpired(FilexusManager $manager): int
    {
        $this->line('Pruning expired files...');

        $count = $manager->pruneExpired();

        if ($count > 0) {
            $this->info("  • Deleted {$count} expired file(s)");
        } else {
            $this->comment('  • No expired files found');
        }

        return $count;
    }

    /**
     * Prune orphaned files.
     *
     * @param FilexusManager $manager
     * @param int|null $hoursOld
     * @return int
     */
    protected function pruneOrphaned(FilexusManager $manager, ?int $hoursOld = null): int
    {
        $hours = $hoursOld ?? config('filexus.orphan_cleanup_hours', 24);

        $this->line("Pruning orphaned files (older than {$hours} hours)...");

        $count = $manager->pruneOrphaned($hoursOld);

        if ($count > 0) {
            $this->info("  • Deleted {$count} orphaned file(s)");
        } else {
            $this->comment('  • No orphaned files found');
        }

        return $count;
    }
}
