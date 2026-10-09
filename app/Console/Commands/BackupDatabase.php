<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BackupDatabase extends Command
{
    protected $signature = 'sistema:respaldar-base {--keep=14 : Cantidad de copias diarias que se conservaran}';

    protected $description = 'Genera una copia consistente de la base de datos SQLite y elimina las mas antiguas.';

    public function handle(): int
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'sqlite') {
            throw new RuntimeException('El respaldo automatico solo esta configurado para SQLite.');
        }

        $directory = storage_path('app/backups');
        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException('No se pudo crear la carpeta de respaldos.');
        }

        $destination = $directory.'/sistema-'.now()->format('Ymd-His').'.sqlite';
        $quotedDestination = $connection->getPdo()->quote($destination);

        // SQLite crea una instantanea consistente, incluso si el sistema tiene actividad.
        $connection->getPdo()->exec("VACUUM INTO {$quotedDestination}");

        $keep = max(1, (int) $this->option('keep'));
        $backups = glob($directory.'/sistema-*.sqlite') ?: [];
        usort($backups, static fn (string $left, string $right): int => filemtime($right) <=> filemtime($left));

        foreach (array_slice($backups, $keep) as $backup) {
            @unlink($backup);
        }

        $this->info("Respaldo creado: {$destination}");

        return self::SUCCESS;
    }
}
