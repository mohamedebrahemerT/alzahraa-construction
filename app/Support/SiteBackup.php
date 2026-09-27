<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class SiteBackup
{
    private const APPLICATION = 'alzahraa-construction';

    public function create(): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('امتداد ZipArchive غير متاح.');
        }

        $directory = storage_path('app/backups');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $driver = DB::getDefaultConnection();
        $name = 'alzahraa-backup-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(6)).'.zip';
        $zipPath = $directory.DIRECTORY_SEPARATOR.$name;
        $temporaryFiles = [];
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('تعذّر إنشاء النسخة الاحتياطية.');
        }

        try {
            if ($driver === 'sqlite') {
                DB::statement('PRAGMA wal_checkpoint(FULL)');
                $databasePath = DB::connection()->getDatabaseName();

                if (! is_file($databasePath) || ! $zip->addFile($databasePath, 'database/database.sqlite')) {
                    throw new RuntimeException('ملف قاعدة البيانات غير موجود أو تعذّر إضافته للنسخة.');
                }
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                [$dumpPath, $credentialsPath] = $this->dumpMysqlDatabase($directory);
                $temporaryFiles = [$dumpPath, $credentialsPath];

                if (! $zip->addFile($dumpPath, 'database/mysql.sql')) {
                    throw new RuntimeException('تعذّر إضافة قاعدة البيانات إلى النسخة الاحتياطية.');
                }
            } else {
                throw new RuntimeException('النسخ الاحتياطي غير مدعوم لمحرك قاعدة البيانات الحالي.');
            }

            foreach (Storage::disk('public')->allFiles() as $file) {
                if (! $zip->addFile(Storage::disk('public')->path($file), 'public/'.$file)) {
                    throw new RuntimeException('تعذّر إضافة أحد ملفات الوسائط إلى النسخة الاحتياطية.');
                }
            }

            $metadata = json_encode([
                'created_at' => now()->toIso8601String(),
                'app' => self::APPLICATION,
                'driver' => $driver,
                'database' => config('database.connections.'.$driver.'.database'),
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            if ($metadata === false || ! $zip->addFromString('backup.json', $metadata) || ! $zip->close()) {
                throw new RuntimeException('تعذّر إكمال ملف النسخة الاحتياطية.');
            }
        } catch (Throwable $exception) {
            $zip->close();
            @unlink($zipPath);

            throw $exception;
        } finally {
            foreach ($temporaryFiles as $path) {
                @unlink($path);
            }
        }

        return $zipPath;
    }

    public function restore(string $archivePath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('ملف النسخة الاحتياطية غير صالح.');
        }

        $temporaryFiles = [];

        try {
            if ($zip->numFiles > 10000) {
                throw new RuntimeException('النسخة الاحتياطية تحتوي على عدد غير مسموح من الملفات.');
            }

            $metadata = json_decode((string) $zip->getFromName('backup.json'), true);
            if (! is_array($metadata) || ($metadata['app'] ?? null) !== self::APPLICATION) {
                throw new RuntimeException('ملف النسخة لا يخص موقع الزهراء.');
            }

            $activeDriver = DB::getDefaultConnection();
            $backupDriver = $metadata['driver'] ?? 'sqlite';

            if ($activeDriver === 'sqlite' && $backupDriver === 'sqlite') {
                $temporaryFiles[] = $this->extractArchiveEntry($zip, 'database/database.sqlite', 'restore-candidate.sqlite');
                $this->restoreSqliteDatabase($temporaryFiles[0]);
            } elseif (in_array($activeDriver, ['mysql', 'mariadb'], true)
                && in_array($backupDriver, ['mysql', 'mariadb'], true)) {
                $temporaryFiles[] = $this->extractArchiveEntry($zip, 'database/mysql.sql', 'restore-candidate.sql');
                $credentialsPath = $this->restoreMysqlDatabase($temporaryFiles[0]);
                $temporaryFiles[] = $credentialsPath;
            } else {
                throw new RuntimeException('محرك قاعدة بيانات النسخة لا يطابق إعداد الموقع الحالي.');
            }

            $this->restorePublicFiles($zip);
        } finally {
            foreach ($temporaryFiles as $path) {
                @unlink($path);
            }
            $zip->close();
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function dumpMysqlDatabase(string $directory): array
    {
        $connection = DB::connection();
        $configuration = $connection->getConfig();
        $database = (string) ($configuration['database'] ?? '');
        if ($database === '') {
            throw new RuntimeException('اسم قاعدة بيانات MySQL غير مضبوط.');
        }

        $dumpPath = tempnam($directory, 'alzahraa-dump-');
        if ($dumpPath === false) {
            throw new RuntimeException('تعذّر تجهيز ملف تفريغ قاعدة البيانات.');
        }

        $credentialsPath = null;
        try {
            $credentialsPath = $this->writeMysqlClientConfig($directory, $configuration);
            $process = new Process([
                (string) config('database.backup.dump_binary', 'mysqldump'),
                '--defaults-extra-file='.$credentialsPath,
                '--default-character-set=utf8mb4',
                '--single-transaction',
                '--skip-lock-tables',
                '--no-tablespaces',
                '--set-gtid-purged=OFF',
                '--hex-blob',
                '--result-file='.$dumpPath,
                $database,
            ]);
            $process->setTimeout(900);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($dumpPath) || filesize($dumpPath) === 0) {
                throw new RuntimeException('تعذّر إنشاء نسخة MySQL. تحقق من مسار mysqldump وصلاحيات قاعدة البيانات.');
            }

            return [$dumpPath, $credentialsPath];
        } catch (Throwable $exception) {
            @unlink($dumpPath);
            if ($credentialsPath !== null) {
                @unlink($credentialsPath);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function writeMysqlClientConfig(string $directory, array $configuration): string
    {
        $credentialsPath = tempnam($directory, 'mysql-client-');
        if ($credentialsPath === false) {
            throw new RuntimeException('تعذّر تجهيز ملف اتصال MySQL المؤقت.');
        }

        $options = [
            'host' => (string) ($configuration['host'] ?? '127.0.0.1'),
            'port' => (string) ($configuration['port'] ?? 3306),
            'user' => (string) ($configuration['username'] ?? ''),
            'password' => (string) ($configuration['password'] ?? ''),
        ];
        if (filled($configuration['unix_socket'] ?? null)) {
            $options['socket'] = (string) $configuration['unix_socket'];
        }

        $contents = "[client]\n";
        foreach ($options as $key => $value) {
            $contents .= $key.'="'.$this->escapeMysqlOption($value)."\"\n";
        }

        if (file_put_contents($credentialsPath, $contents, LOCK_EX) === false) {
            @unlink($credentialsPath);

            throw new RuntimeException('تعذّر إعداد اتصال MySQL الآمن.');
        }
        @chmod($credentialsPath, 0600);

        return $credentialsPath;
    }

    private function escapeMysqlOption(string $value): string
    {
        return str_replace(
            ['\\', '"', "\r", "\n"],
            ['\\\\', '\\"', '\\r', '\\n'],
            $value,
        );
    }

    private function restoreMysqlDatabase(string $dumpPath): string
    {
        $connection = DB::connection();
        $configuration = $connection->getConfig();
        $database = (string) ($configuration['database'] ?? '');
        if ($database === '') {
            throw new RuntimeException('اسم قاعدة بيانات MySQL غير مضبوط.');
        }

        $credentialsPath = $this->writeMysqlClientConfig(storage_path('app/backups'), $configuration);
        $process = new Process([
            (string) config('database.backup.client_binary', 'mysql'),
            '--defaults-extra-file='.$credentialsPath,
            '--default-character-set=utf8mb4',
            '--binary-mode=1',
            '--database='.$database,
        ]);
        $process->setTimeout(900);
        $input = fopen($dumpPath, 'rb');
        if ($input === false) {
            @unlink($credentialsPath);

            throw new RuntimeException('تعذّر قراءة نسخة قاعدة البيانات.');
        }

        try {
            $process->setInput($input);
            $process->run();
        } catch (Throwable $exception) {
            @unlink($credentialsPath);

            throw $exception;
        } finally {
            fclose($input);
        }

        if (! $process->isSuccessful()) {
            @unlink($credentialsPath);

            throw new RuntimeException('تعذّرت استعادة قاعدة MySQL. تحقق من صلاحيات قاعدة البيانات.');
        }

        return $credentialsPath;
    }

    private function restoreSqliteDatabase(string $candidate): void
    {
        $pdo = new \PDO('sqlite:'.$candidate);
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(\PDO::FETCH_COLUMN);
        foreach (['migrations', 'users', 'content_pages', 'content_items', 'estimate_requests'] as $table) {
            if (! in_array($table, $tables, true)) {
                throw new RuntimeException('النسخة لا تحتوي على جداول موقع الزهراء المطلوبة.');
            }
        }
        $pdo = null;
        $databasePath = DB::connection()->getDatabaseName();
        DB::disconnect();
        if (! copy($candidate, $databasePath)) {
            throw new RuntimeException('تعذّر استعادة قاعدة البيانات.');
        }
        DB::purge();
    }

    private function extractArchiveEntry(ZipArchive $zip, string $entry, string $filename): string
    {
        $stream = $zip->getStream($entry);
        if ($stream === false) {
            throw new RuntimeException('النسخة الاحتياطية لا تحتوي على قاعدة البيانات.');
        }

        $directory = storage_path('app/backups');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $candidate = $directory.DIRECTORY_SEPARATOR.$filename;
        $target = fopen($candidate, 'wb');
        if ($target === false) {
            fclose($stream);

            throw new RuntimeException('تعذّر تجهيز ملف الاستعادة.');
        }

        try {
            if (stream_copy_to_stream($stream, $target) === false) {
                throw new RuntimeException('تعذّر استخراج قاعدة البيانات من النسخة.');
            }
        } catch (Throwable $exception) {
            @unlink($candidate);

            throw $exception;
        } finally {
            fclose($target);
            fclose($stream);
        }

        return $candidate;
    }

    private function restorePublicFiles(ZipArchive $zip): void
    {
        $disk = Storage::disk('public');
        foreach ($disk->allFiles() as $file) {
            $disk->delete($file);
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = $zip->getNameIndex($index);
            if (! str_starts_with($entry, 'public/')) {
                continue;
            }

            $relative = substr($entry, 7);
            if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/') || str_contains($relative, '\\')) {
                continue;
            }

            $input = $zip->getStream($entry);
            if ($input === false) {
                continue;
            }

            $disk->put($relative, $input);
            fclose($input);
        }
    }
}
