<?php

namespace App\Console\Commands;

use App\Support\DocumentStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Moves client documents off the publicly served disk onto the private one.
 *
 * Passport scans, government IDs, birth certificates and profile photos were
 * previously written to storage/app/public, which is symlinked into the web
 * root and handed out by the web server to anyone who knows the URL. This
 * command relocates the existing files so the new private-only routes work.
 *
 * The original is deleted only after the private copy is confirmed present, so
 * a failure part way through leaves the public copy intact.
 */
class PrivatiseDocuments extends Command
{
    protected $signature = 'documents:privatise
                            {--dry-run : List what would move, touching nothing}
                            {--keep-originals : Copy across but leave the public copies in place}';

    protected $description = 'Move client documents from the public disk to the private document disk';

    /**
     * The folders that hold client identity documents.
     *
     * @var list<string>
     */
    private const FOLDERS = ['ids', 'profiles', 'tickets'];

    public function handle(): int
    {
        $diskName = DocumentStorage::diskName();

        if ($diskName === 'public') {
            $this->error('DOCUMENTS_DISK is set to "public". That is the exposure this command exists to fix.');
            $this->line('Set DOCUMENTS_DISK=local (or another private disk) and run again.');

            return self::FAILURE;
        }

        $source = Storage::disk('public');
        $target = DocumentStorage::disk();

        $dryRun = (bool) $this->option('dry-run');
        $keepOriginals = (bool) $this->option('keep-originals');

        $this->info("Moving client documents: public -> {$diskName}");

        if ($dryRun) {
            $this->warn('Dry run. Nothing will be changed.');
        }

        $moved = 0;
        $alreadyPrivate = 0;
        $failed = 0;

        foreach (self::FOLDERS as $folder) {
            if (! $source->exists($folder)) {
                continue;
            }

            $files = $source->allFiles($folder);

            if ($files === []) {
                continue;
            }

            $this->line('');
            $this->line("  {$folder}/ (".count($files).' file(s))');

            foreach ($files as $path) {
                if ($target->exists($path)) {
                    $alreadyPrivate++;
                    $this->line("    skip      {$path}");

                    continue;
                }

                if ($dryRun) {
                    $moved++;
                    $this->line("    would move {$path}");

                    continue;
                }

                if (! $this->transfer($source, $target, $path)) {
                    $failed++;
                    $this->line("    <fg=red>failed</>    {$path}");

                    continue;
                }

                if (! $keepOriginals) {
                    $source->delete($path);
                }

                $moved++;
            }
        }

        $this->line('');
        $this->table(
            ['Moved', 'Already private', 'Failed'],
            [[$moved, $alreadyPrivate, $failed]]
        );

        if ($failed > 0) {
            $this->error("{$failed} file(s) could not be moved. The public copies were left untouched.");

            return self::FAILURE;
        }

        if ($moved === 0 && $alreadyPrivate === 0) {
            $this->info('Nothing to move.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("{$moved} file(s) would move.");

            return self::SUCCESS;
        }

        if ($keepOriginals) {
            $this->warn('Originals kept on the public disk. Remove them once you have verified the private copies.');

            return self::SUCCESS;
        }

        $this->info("Done. {$moved} file(s) now served privately.");
        $this->line('You can remove the now-empty public folders and re-run storage:link if you wish.');

        return self::SUCCESS;
    }

    /**
     * Copy one file across and confirm it landed before the caller deletes it.
     */
    private function transfer($source, $target, string $path): bool
    {
        $stream = $source->readStream($path);

        if ($stream === null) {
            return false;
        }

        $written = $target->writeStream($path, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        return $written && $target->exists($path);
    }
}
