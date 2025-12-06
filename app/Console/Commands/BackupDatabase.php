<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database';
    protected $description = 'Dump database to storage/app/backups and cleanup files older than 30 days';

    public function handle(): int
    {
        $driver = config('database.default');
        $conn = config('database.connections.' . $driver);
        $dir = storage_path('app/backups');
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $timestamp = now()->format('Ymd_His');
        $filename = $dir . '/backup_' . $driver . '_' . $timestamp . '.sql';

        try {
            if ($driver === 'sqlite') {
                $dbPath = database_path('database.sqlite');
                $cmd = 'sqlite3 ' . escapeshellarg($dbPath) . ' .dump > ' . escapeshellarg($filename);
                exec($cmd, $out, $status);
                if ($status !== 0) throw new \RuntimeException('sqlite3 dump failed');
            } elseif ($driver === 'mysql') {
                $cmd = sprintf(
                    'mysqldump -h %s -u %s -p%s %s > %s',
                    escapeshellarg($conn['host'] ?? '127.0.0.1'),
                    escapeshellarg($conn['username'] ?? ''),
                    $conn['password'] ?? '',
                    escapeshellarg($conn['database'] ?? ''),
                    escapeshellarg($filename)
                );
                exec($cmd, $out, $status);
                if ($status !== 0) throw new \RuntimeException('mysqldump failed');
            } elseif ($driver === 'pgsql') {
                $env = [
                    'PGPASSWORD' => $conn['password'] ?? '',
                ];
                foreach ($env as $k => $v) putenv($k.'='.$v);
                $cmd = sprintf(
                    'pg_dump -h %s -U %s -d %s -F p > %s',
                    escapeshellarg($conn['host'] ?? '127.0.0.1'),
                    escapeshellarg($conn['username'] ?? ''),
                    escapeshellarg($conn['database'] ?? ''),
                    escapeshellarg($filename)
                );
                exec($cmd, $out, $status);
                if ($status !== 0) throw new \RuntimeException('pg_dump failed');
            } else {
                $tables = DB::select('SELECT name FROM sqlite_master WHERE type="table"');
                $path = $filename;
                file_put_contents($path, json_encode($tables));
            }

            $this->info('Backup created: ' . basename($filename));
            Log::info('backup_success', ['file' => basename($filename)]);
        } catch (\Throwable $e) {
            Log::error('backup_failed', ['error' => $e->getMessage()]);
            $this->error('Backup failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        try {
            $files = Storage::disk('local')->files('backups');
            foreach ($files as $f) {
                $mtime = Storage::disk('local')->lastModified($f);
                if ($mtime < now()->subDays(30)->getTimestamp()) {
                    Storage::disk('local')->delete($f);
                }
            }
        } catch (\Throwable $e) {}

        return self::SUCCESS;
    }
}

