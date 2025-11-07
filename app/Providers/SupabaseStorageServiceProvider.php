<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter as LaravelFilesystemAdapter;
use App\Services\SupabaseStorageAdapter;

class SupabaseStorageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Storage::extend('supabase', function ($app, $config) {
            $adapter = new SupabaseStorageAdapter($config);
            $filesystem = new Filesystem($adapter);
            return new LaravelFilesystemAdapter($filesystem, $adapter, $config);
        });
    }
}
