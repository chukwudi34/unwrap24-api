<?php

namespace App\Providers;

use App\Billing\PaymentGatewayFactory;
use App\Models\Calendar;
use App\Policies\CalendarPolicy;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayFactory::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Calendar::class, CalendarPolicy::class);

        $this->registerGoogleCloudStorageDriver();
    }

    /**
     * Register the `gcs` filesystem driver used by the Cloud Storage disk.
     *
     * Authentication falls back to Application Default Credentials, so on
     * Cloud Run the runtime service account is used without a key file.
     */
    private function registerGoogleCloudStorageDriver(): void
    {
        Storage::extend('gcs', function ($app, array $config): FilesystemAdapter {
            $client = new StorageClient(array_filter([
                'projectId' => $config['project_id'] ?? null,
                'keyFilePath' => $config['key_file_path'] ?? null,
            ]));

            $adapter = new GoogleCloudStorageAdapter(
                $client->bucket($config['bucket']),
                $config['path_prefix'] ?? '',
            );

            return new FilesystemAdapter(new Flysystem($adapter, $config), $adapter, $config);
        });
    }
}
