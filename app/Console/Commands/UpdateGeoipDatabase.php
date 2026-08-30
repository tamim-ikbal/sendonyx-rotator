<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use MaxMind\Db\Reader;
use Throwable;

/**
 * Fetches the country database the click recorder falls back to.
 *
 * The file is CC-BY licensed and around 8MB, so it is downloaded per
 * environment rather than committed. DB-IP publish a new one on the first of
 * each month at a predictable url and leave the previous months in place.
 */
final class UpdateGeoipDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rotator:geoip-update
                            {--month= : The YYYY-MM edition to fetch, defaulting to this month}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download the DB-IP Lite country database used to geolocate clicks';

    /**
     * Where the monthly editions are published.
     */
    private const URL = 'https://download.db-ip.com/free/dbip-country-lite-%s.mmdb.gz';

    /**
     * Execute the console command.
     *
     * The download is written beside the live database and only moved over it
     * once it has been decompressed and opened successfully, so a failed or
     * truncated run leaves the previous month's file serving lookups.
     */
    public function handle(): int
    {
        $destination = config()->string('rotator.geo.database');

        File::ensureDirectoryExists(dirname($destination));

        $archive = $destination.'.gz';
        $candidate = $destination.'.candidate';

        try {
            foreach ($this->editions() as $edition) {
                if ($this->download($edition, $archive)) {
                    return $this->install($archive, $candidate, $destination, $edition);
                }

                $this->components->warn("No edition published for {$edition}.");
            }

            $this->components->error('Could not download a country database.');

            return self::FAILURE;
        } finally {
            File::delete([$archive, $candidate]);
        }
    }

    /**
     * The editions to try, newest first.
     *
     * A run early on the first of the month can beat the new file being
     * published, and the schedule is not worth failing over a few hours, so the
     * previous month is accepted as a fallback.
     *
     * @return array<int, string>
     */
    private function editions(): array
    {
        $month = $this->option('month');

        if (is_string($month) && $month !== '') {
            return [$month];
        }

        $now = CarbonImmutable::now();

        return [$now->format('Y-m'), $now->subMonthNoOverflow()->format('Y-m')];
    }

    /**
     * Stream one edition to disk, reporting whether it was published.
     */
    private function download(string $edition, string $archive): bool
    {
        $published = false;

        // A missing edition answers 404 with a body, and the sink writes that
        // body to disk like any other, so the status is what decides this and
        // not whether a file appeared.
        $this->components->task("Downloading the {$edition} edition", function () use ($edition, $archive, &$published): bool {
            $published = Http::timeout(300)->sink($archive)->get(sprintf(self::URL, $edition))->successful();

            return $published;
        });

        return $published && File::exists($archive) && File::size($archive) > 0;
    }

    /**
     * Decompress, verify and move the download into place.
     */
    private function install(string $archive, string $candidate, string $destination, string $edition): int
    {
        if (! $this->decompress($archive, $candidate)) {
            $this->components->error('The download could not be decompressed.');

            return self::FAILURE;
        }

        if (! $this->opens($candidate)) {
            $this->components->error('The download is not a readable country database.');

            return self::FAILURE;
        }

        File::move($candidate, $destination);

        $this->components->info("Installed the {$edition} edition at {$destination}.");

        // Workers memory map the database once and hold it open, so a running
        // worker keeps serving lookups from the file this just replaced.
        Artisan::call('queue:restart');

        $this->components->info('Told the queue workers to restart. IP Geolocation by DB-IP (https://db-ip.com), CC BY 4.0.');

        return self::SUCCESS;
    }

    /**
     * Expand the gzipped download, a chunk at a time.
     *
     * The uncompressed file is large enough that reading it into a string to
     * write it back out again is worth avoiding on a small worker.
     */
    private function decompress(string $archive, string $candidate): bool
    {
        $source = @gzopen($archive, 'rb');

        if ($source === false) {
            return false;
        }

        $target = @fopen($candidate, 'wb');

        if ($target === false) {
            gzclose($source);

            return false;
        }

        while (! gzeof($source)) {
            $chunk = gzread($source, 262144);

            if ($chunk === false || fwrite($target, $chunk) === false) {
                gzclose($source);
                fclose($target);

                return false;
            }
        }

        gzclose($source);
        fclose($target);

        return File::size($candidate) > 0;
    }

    /**
     * Confirm the file is a country database before it replaces the live one.
     *
     * Opening the reader parses the metadata at the end of the file, so this
     * catches a truncated download, and the lookup confirms it is a country
     * database rather than one of the other editions published in this format.
     */
    private function opens(string $candidate): bool
    {
        try {
            $database = new Reader($candidate);
            $record = $database->get('1.1.1.1');
            $database->close();
        } catch (Throwable) {
            return false;
        }

        return is_array($record) && isset($record['country']);
    }
}
