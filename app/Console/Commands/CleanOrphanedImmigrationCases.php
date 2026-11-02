<?php

namespace App\Console\Commands;

use App\Models\ImmigrationClient;
use App\Models\ImmigrationClientDocument;
use App\Models\ImmigrationClientExtension;
use App\Models\ImmigrationOverstay;
use App\Models\ImmigrationPetition;
use App\Models\ImmigrationRestamping;
use App\Support\DocumentStorage;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Removes immigration cases, extensions and documents whose client folder no longer exists.
 *
 * Deleting a folder used to rely on the database to remove what belonged to it. A MyISAM table (the live
 * host's default) ignores foreign keys, so the cases were left behind as "Unknown client" files that nobody
 * could open. They are already hidden from every list and count; this clears them out of the database.
 * Nothing is deleted without being listed first and confirmed, and a scan file is deleted with its row.
 */
class CleanOrphanedImmigrationCases extends Command
{
    protected $signature = 'immigration:clean-orphans
                            {--dry-run : List what would be deleted, touching nothing}
                            {--force : Delete without asking first}';

    protected $description = 'Delete immigration cases, extensions and documents whose client folder was deleted';

    /** @var array<string, class-string<Model>> */
    private const MODELS = [
        '13(A), ECC, dual citizenship and downgrading cases' => ImmigrationPetition::class,
        'Visa overstaying cases' => ImmigrationOverstay::class,
        'Re-stamping cases' => ImmigrationRestamping::class,
        'Visa extensions' => ImmigrationClientExtension::class,
        'Client documents' => ImmigrationClientDocument::class,
    ];

    public function handle(): int
    {
        $found = [];
        foreach (self::MODELS as $label => $model) {
            $count = $this->orphans($model)->count();
            $found[$label] = $count;
        }

        $this->table(['What', 'Without a client folder'], collect($found)->map(fn (int $count, string $label) => [$label, $count])->values()->all());

        if (array_sum($found) === 0) {
            $this->info('Nothing to clean: every case has its client folder.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run. Nothing was deleted.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Delete these '.array_sum($found).' records for good? Take a database backup first.')) {
            $this->line('Nothing was deleted.');

            return self::SUCCESS;
        }

        $deleted = 0;
        foreach (self::MODELS as $model) {
            // A scan goes with its row.
            if ($model === ImmigrationClientDocument::class) {
                $this->orphans($model)->get()->each(function (ImmigrationClientDocument $document) {
                    if ($document->file_path) {
                        DocumentStorage::disk()->delete($document->file_path);
                    }
                });
            }
            $deleted += $this->orphans($model)->delete();
        }

        $this->info("Deleted {$deleted} records.");

        return self::SUCCESS;
    }

    /**
     * The model's rows with no client folder, whoever is signed in.
     *
     * @param  class-string<Model>  $model
     */
    private function orphans(string $model): Builder
    {
        return $model::withoutGlobalScopes()
            ->whereNotIn('immigration_client_id', ImmigrationClient::withoutGlobalScopes()->select('id'));
    }
}
