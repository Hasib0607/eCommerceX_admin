<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;

class BackupManager
{
    public function statusFile(): string
    {
        return storage_path('app/backup-status.json');
    }

    public function setStatus(string $type, string $status, int $progress, string $message): void
    {
        file_put_contents($this->statusFile(), json_encode([
            'type' => $type,
            'status' => $status,
            'progress' => $progress,
            'message' => $message,
            'time' => now()->toDateTimeString(),
        ]));
    }

    public function ensureDirectory(string $path): void
    {
        if (!file_exists($path)) {
            mkdir($path, 0775, true);
        }
    }

    public function backupDatabase(): void
    {
        $backupDir = storage_path('app/backups/database');
        $this->ensureDirectory($backupDir);

        $fileName = 'database-backup-' . date('Y-m-d-H-i-s') . '.sql';
        $filePath = $backupDir . DIRECTORY_SEPARATOR . $fileName;

        $command = sprintf(
            'mysqldump --user=%s --password=%s %s > %s 2>&1',
            escapeshellarg(env('DB_USERNAME')),
            escapeshellarg(env('DB_PASSWORD')),
            escapeshellarg(env('DB_DATABASE')),
            escapeshellarg($filePath)
        );

        exec($command, $output, $resultCode);

        if ($resultCode !== 0 || !file_exists($filePath) || filesize($filePath) <= 0) {
            throw new \Exception('Database backup failed.');
        }
    }

    public function backupCode(): void
    {
        $backupDir = storage_path('app/backups/code');
        $this->ensureDirectory($backupDir);

        $fileName = 'code-backup-' . date('Y-m-d-H-i-s') . '.zip';
        $filePath = $backupDir . DIRECTORY_SEPARATOR . $fileName;

        $command = sprintf(
            "cd %s && zip -rq %s . "
            . "-x 'public/*' "
            . "-x 'node_modules/*' "
            . "-x 'storage/app/backups/*' "
            . "-x 'storage/app/backup-tmp/*' "
            . "-x 'storage/app/public/*' "
            . "2>&1",
            escapeshellarg(base_path()),
            escapeshellarg($filePath)
        );

        exec($command, $output, $resultCode);

        if ($resultCode !== 0 || !file_exists($filePath) || filesize($filePath) <= 0) {
            throw new \Exception('Code backup failed.');
        }
    }

    public function backupPublic(): void
    {
        $backupDir = storage_path('app/backups/public');
        $this->ensureDirectory($backupDir);

        $fileName = 'public-backup-' . date('Y-m-d-H-i-s') . '.zip';
        $filePath = $backupDir . DIRECTORY_SEPARATOR . $fileName;

        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        $command = sprintf(
            "cd %s && zip -rq %s public "
            . "-x 'storage/app/backups/*' "
            . "-x 'storage/app/backup-tmp/*' "
            . "2>&1",
            escapeshellarg(base_path()),
            escapeshellarg($filePath)
        );

        exec($command, $output, $resultCode);

        if ($resultCode !== 0) {
            throw new \Exception('Public backup failed: ' . implode("\n", $output));
        }

        clearstatcache();

        if (!file_exists($filePath)) {
            throw new \Exception('Public backup file was not created.');
        }

        if (filesize($filePath) <= 0) {
            @unlink($filePath);
            throw new \Exception('Public backup file is empty.');
        }
    }

    public function googleClient(): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId(env('GOOGLE_DRIVE_CLIENT_ID'));
        $client->setClientSecret(env('GOOGLE_DRIVE_CLIENT_SECRET'));
        $client->addScope(GoogleDrive::DRIVE);
        $client->fetchAccessTokenWithRefreshToken(env('GOOGLE_DRIVE_REFRESH_TOKEN'));

        return $client;
    }

    public function cleanupSelected(array $types, int $days): array
    {
        $types = array_values(array_intersect(['database', 'code', 'public'], $types));

        if (empty($types)) {
            throw new \Exception('Please select at least one backup type.');
        }

        $folderMap = [
            'database' => storage_path('app/backups/database'),
            'code' => storage_path('app/backups/code'),
            'public' => storage_path('app/backups/public'),
        ];

        $prefixMap = [
            'database' => 'database-backup-',
            'code' => 'code-backup-',
            'public' => 'public-backup-',
        ];

        $localDeleted = 0;
        $cutoffLocal = strtotime("-{$days} days");

        foreach ($types as $type) {
            $folder = $folderMap[$type] ?? null;

            if (!$folder || !is_dir($folder)) {
                continue;
            }

            foreach (File::allFiles($folder) as $file) {
                if ($file->getMTime() < $cutoffLocal) {
                    @unlink($file->getPathname());
                    $localDeleted++;
                }
            }
        }

        $client = $this->googleClient();
        $drive = new GoogleDrive($client);

        $rootFolderId = env('GOOGLE_DRIVE_FOLDER');
        $cutoffDate = now()->subDays($days)->format('Y-m-d');
        $driveDeleted = 0;

        $folders = $drive->files->listFiles([
            'q' => sprintf(
                "'%s' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
                $rootFolderId
            ),
            'fields' => 'files(id,name)',
            'pageSize' => 200,
        ]);

        foreach ($folders->files as $folder) {
            $folderName = $folder->name;

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $folderName)) {
                continue;
            }

            if ($folderName >= $cutoffDate) {
                continue;
            }

            $files = $drive->files->listFiles([
                'q' => sprintf("'%s' in parents and trashed = false", $folder->id),
                'fields' => 'files(id,name)',
                'pageSize' => 200,
            ]);

            foreach ($files->files as $file) {
                foreach ($types as $type) {
                    $prefix = $prefixMap[$type];

                    if (str_starts_with($file->name, $prefix)) {
                        $drive->files->delete($file->id);
                        $driveDeleted++;
                        break;
                    }
                }
            }

            $remainingFiles = $drive->files->listFiles([
                'q' => sprintf("'%s' in parents and trashed = false", $folder->id),
                'fields' => 'files(id)',
                'pageSize' => 5,
            ]);

            if (empty($remainingFiles->files)) {
                $drive->files->delete($folder->id);
            }
        }

        return [
            'local' => $localDeleted,
            'drive' => $driveDeleted,
        ];
    }
}