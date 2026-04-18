<?php

namespace App\Providers;

use App\Services\NamespaceResolver;
use App\Services\ScaffoldService;
use Illuminate\Support\ServiceProvider;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FilesystemLoader::class, function (): FilesystemLoader {
            return new FilesystemLoader(app_path('Stubs'));
        });

        $this->app->singleton(Environment::class, function ($app): Environment {
            return new Environment(
                $app->make(FilesystemLoader::class),
                [
                    'autoescape' => false,
                    'cache' => false,
                    'strict_variables' => true,
                    'trim_blocks' => true,
                    'lstrip_blocks' => true,
                ]
            );
        });

        $this->app->singleton(NamespaceResolver::class, fn (): NamespaceResolver => new NamespaceResolver);

        $this->app->singleton(
            ScaffoldService::class,
            fn ($app): ScaffoldService => new ScaffoldService(
                $app->make(Environment::class),
                $app->make(NamespaceResolver::class),
            )
        );
    }
}
