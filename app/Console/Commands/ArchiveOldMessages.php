<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ArchiveOldMessages extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'messages:archive 
                            {--months=6 : Archive messages older than X months}
                            {--delete : Delete archived messages from main table}
                            {--dry-run : Show what would be archived without actually doing it}';

    /**
     * The console command description.
     */
    protected $description = 'Archive old messages to reduce database bloat';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $months = (int) $this->option('months');
        $delete = $this->option('delete');
        $dryRun = $this->option('dry-run');

        $cutoffDate = Carbon::now()->subMonths($months);

        $this->info("🐱 MangOyen Message Archiver");
        $this->info("================================");
        $this->info("Archiving messages older than: {$cutoffDate->format('Y-m-d')}");

        // Count messages to archive
        $count = DB::table('messages')
            ->where('created_at', '<', $cutoffDate)
            ->count();

        if ($count === 0) {
            $this->info("✅ No messages to archive. Database is clean!");
            return 0;
        }

        $this->info("Found {$count} messages to archive");

        if ($dryRun) {
            $this->warn("🔍 DRY RUN - No changes will be made");

            // Show sample of messages
            $samples = DB::table('messages')
                ->where('created_at', '<', $cutoffDate)
                ->select('id', 'adoption_id', 'created_at')
                ->limit(5)
                ->get();

            $this->table(['ID', 'Adoption ID', 'Created At'], $samples);

            return 0;
        }

        if (!$this->confirm("Do you want to archive {$count} messages?")) {
            $this->info("Operation cancelled.");
            return 0;
        }

        $this->info("Archiving messages...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        // Archive in batches
        $batchSize = 1000;
        $archived = 0;

        DB::table('messages')
            ->where('created_at', '<', $cutoffDate)
            ->orderBy('id')
            ->chunk($batchSize, function ($messages) use (&$archived, $bar, $delete) {
                $archiveData = [];
                $idsToDelete = [];

                foreach ($messages as $message) {
                    $archiveData[] = [
                        'original_id' => $message->id,
                        'adoption_id' => $message->adoption_id,
                        'sender_id' => $message->sender_id,
                        'content' => $message->content,
                        'is_censored' => $message->is_censored ?? false,
                        'read_at' => $message->read_at,
                        'original_created_at' => $message->created_at,
                        'archived_at' => now(),
                    ];

                    $idsToDelete[] = $message->id;
                    $archived++;
                    $bar->advance();
                }

                // Insert to archive
                DB::table('messages_archive')->insert($archiveData);

                // Delete from main table if requested
                if ($delete) {
                    DB::table('messages')->whereIn('id', $idsToDelete)->delete();
                }
            });

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Archived {$archived} messages");

        if ($delete) {
            $this->info("🗑️  Deleted archived messages from main table");
        } else {
            $this->warn("⚠️  Messages still exist in main table. Run with --delete to remove");
        }

        // Show stats
        $mainCount = DB::table('messages')->count();
        $archiveCount = DB::table('messages_archive')->count();

        $this->newLine();
        $this->info("📊 Database Stats:");
        $this->info("   - Main messages table: {$mainCount} rows");
        $this->info("   - Archive table: {$archiveCount} rows");

        return 0;
    }
}
