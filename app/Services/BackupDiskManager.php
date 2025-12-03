<?php

namespace App\Services;

use App\Models\BackupDisk;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class BackupDiskManager
{
    protected const CACHE_KEY = 'backup_disks_config';

    /**
     * Load and configure all backup disks from database
     */
    public function loadDisks(): void
    {
        $disks = BackupDisk::where('is_active', true)->get();

        $config = [];

        foreach ($disks as $disk) {
            $config[$disk->name] = $this->getDiskConfig($disk);
        }

        // Merge with existing config
        Config::set('filesystems.disks', array_merge(
            config('filesystems.disks', []),
            $config
        ));

        // Cache the configuration
        Cache::put(self::CACHE_KEY, $config, 3600);
    }

    /**
     * Get disk configuration for a specific disk
     */
    protected function getDiskConfig(BackupDisk $disk): array
    {
        $config = $disk->config;

        // Ensure driver is set
        $diskConfig = [
            'driver' => $disk->driver,
        ];

        // Add driver-specific configuration
        switch ($disk->driver) {
            case 'local':
                $diskConfig['root'] = $config['root'] ?? storage_path('app/private/backups');
                break;

            case 's3':
                $diskConfig = array_merge($diskConfig, [
                    'key' => $config['key'] ?? '',
                    'secret' => $config['secret'] ?? '',
                    'region' => $config['region'] ?? '',
                    'bucket' => $config['bucket'] ?? '',
                    'url' => $config['url'] ?? null,
                    'endpoint' => $config['endpoint'] ?? null,
                    'use_path_style_endpoint' => $config['use_path_style_endpoint'] ?? false,
                ]);
                break;

            case 'digitalocean':
                // S3-compatible DigitalOcean Spaces
                $diskConfig = array_merge($diskConfig, [
                    'driver' => 's3',
                    'key' => $config['key'] ?? '',
                    'secret' => $config['secret'] ?? '',
                    'region' => $config['region'] ?? '',
                    'bucket' => $config['bucket'] ?? '',
                    'endpoint' => $config['endpoint'] ?? 'https://' . $config['region'] . '.digitaloceanspaces.com',
                    'use_path_style_endpoint' => true,
                ]);
                break;

            case 'wasabi':
                // S3-compatible Wasabi
                $diskConfig = array_merge($diskConfig, [
                    'driver' => 's3',
                    'key' => $config['key'] ?? '',
                    'secret' => $config['secret'] ?? '',
                    'region' => $config['region'] ?? '',
                    'bucket' => $config['bucket'] ?? '',
                    'endpoint' => $config['endpoint'] ?? 'https://s3.' . $config['region'] . '.wasabisys.com',
                    'use_path_style_endpoint' => true,
                ]);
                break;

            case 'backblaze':
                // S3-compatible Backblaze B2
                $diskConfig = array_merge($diskConfig, [
                    'driver' => 's3',
                    'key' => $config['key'] ?? '',
                    'secret' => $config['secret'] ?? '',
                    'region' => $config['region'] ?? 'us-east-1',
                    'bucket' => $config['bucket'] ?? '',
                    'endpoint' => $config['endpoint'] ?? 'https://s3.' . $config['region'] . '.backblazeb2.com',
                    'use_path_style_endpoint' => true,
                ]);
                break;

            default:
                // For custom S3-compatible providers
                if (isset($config['endpoint'])) {
                    $diskConfig = array_merge($diskConfig, [
                        'driver' => 's3',
                        'key' => $config['key'] ?? '',
                        'secret' => $config['secret'] ?? '',
                        'region' => $config['region'] ?? 'us-east-1',
                        'bucket' => $config['bucket'] ?? '',
                        'endpoint' => $config['endpoint'],
                        'use_path_style_endpoint' => $config['use_path_style_endpoint'] ?? true,
                    ]);
                }
                break;
        }

        return $diskConfig;
    }

    /**
     * Get the default backup disk
     */
    public function getDefaultDisk(): ?BackupDisk
    {
        return BackupDisk::where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get a disk by ID
     */
    public function getDiskById(int $id): ?BackupDisk
    {
        return BackupDisk::where('id', $id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get all active disks
     */
    public function getAllDisks(): \Illuminate\Database\Eloquent\Collection
    {
        return BackupDisk::where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Test disk connection
     */
    public function testDisk(BackupDisk $disk): bool
    {
        try {
            // Refresh disk configuration
            $config = $this->getDiskConfig($disk);
            Config::set("filesystems.disks.{$disk->name}", $config);

            $diskInstance = Storage::disk($disk->name);

            // Try to write and read a test file
            $testFile = 'test-' . time() . '.txt';
            $diskInstance->put($testFile, 'test');

            if ($diskInstance->exists($testFile)) {
                $diskInstance->delete($testFile);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            \Log::error('Disk test failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Initialize default local disk if none exists
     */
    public function initializeDefaultDisk(): void
    {
        $existingDisks = BackupDisk::count();

        if ($existingDisks === 0) {
            BackupDisk::create([
                'name' => 'backups',
                'driver' => 'local',
                'config' => [
                    'root' => storage_path('app/private/backups'),
                ],
                'is_default' => true,
                'is_active' => true,
            ]);
        }
    }
}
