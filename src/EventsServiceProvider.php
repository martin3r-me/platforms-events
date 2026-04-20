<?php

/**
 * Events Service Provider
 *
 * @see Platform\Core\PlatformCore für Modul-Registrierung
 * @see Platform\Core\Routing\ModuleRouter für Route-Registrierung
 */

namespace Platform\Events;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class EventsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/events.php', 'events');
    }

    public function boot(): void
    {
        if (
            config()->has('events.routing') &&
            config()->has('events.navigation') &&
            Schema::hasTable('modules')
        ) {
            PlatformCore::registerModule([
                'key'        => 'events',
                'title'      => 'Events',
                'group'      => 'sales',
                'routing'    => config('events.routing'),
                'guard'      => config('events.guard'),
                'navigation' => config('events.navigation'),
                'sidebar'    => config('events.sidebar'),
            ]);
        }

        if (PlatformCore::getModule('events')) {
            ModuleRouter::group('events', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/events.php' => config_path('events.php'),
        ], 'config');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'events');

        $this->registerLivewireComponents();

        // Tools registrieren (loose gekoppelt - für AI/Chat)
        $this->registerTools();
    }

    /**
     * Registriert Events-Tools für die AI/Chat-Funktionalität.
     * Tools kommen in einem späteren Schritt – hier nur die Hook-Stelle.
     */
    protected function registerTools(): void
    {
        try {
            if (!class_exists(\Platform\Core\Tools\ToolRegistry::class)) {
                return;
            }

            // $registry = resolve(\Platform\Core\Tools\ToolRegistry::class);
            // Tools werden in einem Folge-Commit registriert.
        } catch (\Throwable $e) {
            // Silent fail – Tool-Registry ggf. noch nicht verfügbar
        }
    }

    protected function registerLivewireComponents(): void
    {
        $basePath = __DIR__ . '/Livewire';
        $baseNamespace = 'Platform\\Events\\Livewire';
        $prefix = 'events';

        if (!is_dir($basePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $classPath = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $class = $baseNamespace . '\\' . $classPath;

            if (!class_exists($class)) {
                continue;
            }

            $aliasPath = str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            $alias = $prefix . '.' . $aliasPath;

            Livewire::component($alias, $class);
        }
    }
}
