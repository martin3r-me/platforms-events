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

        // Public-Token-Routen (kein Auth, kein Modul-Prefix)
        $this->loadRoutesFrom(__DIR__ . '/../routes/public.php');

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
     */
    protected function registerTools(): void
    {
        try {
            if (!class_exists(\Platform\Core\Tools\ToolRegistry::class)) {
                return;
            }

            $registry = resolve(\Platform\Core\Tools\ToolRegistry::class);

            // --- Events (voll inkl. Bulk) ---
            $registry->register(new \Platform\Events\Tools\ListEventsTool());
            $registry->register(new \Platform\Events\Tools\GetEventTool());
            $registry->register(new \Platform\Events\Tools\CreateEventTool());
            $registry->register(new \Platform\Events\Tools\UpdateEventTool());
            $registry->register(new \Platform\Events\Tools\DeleteEventTool());
            $registry->register(new \Platform\Events\Tools\BulkCreateEventsTool());
            $registry->register(new \Platform\Events\Tools\BulkUpdateEventsTool());
            $registry->register(new \Platform\Events\Tools\BulkDeleteEventsTool());
            $registry->register(new \Platform\Events\Tools\CloneEventTool());

            // --- EventDays (Single-CUD) ---
            $registry->register(new \Platform\Events\Tools\ListEventDaysTool());
            $registry->register(new \Platform\Events\Tools\GetEventDayTool());
            $registry->register(new \Platform\Events\Tools\CreateEventDayTool());
            $registry->register(new \Platform\Events\Tools\UpdateEventDayTool());
            $registry->register(new \Platform\Events\Tools\DeleteEventDayTool());

            // --- Bookings (Single-CUD) ---
            $registry->register(new \Platform\Events\Tools\ListBookingsTool());
            $registry->register(new \Platform\Events\Tools\GetBookingTool());
            $registry->register(new \Platform\Events\Tools\CreateBookingTool());
            $registry->register(new \Platform\Events\Tools\UpdateBookingTool());
            $registry->register(new \Platform\Events\Tools\DeleteBookingTool());

            // --- ScheduleItems (Single-CUD) ---
            $registry->register(new \Platform\Events\Tools\ListScheduleItemsTool());
            $registry->register(new \Platform\Events\Tools\GetScheduleItemTool());
            $registry->register(new \Platform\Events\Tools\CreateScheduleItemTool());
            $registry->register(new \Platform\Events\Tools\UpdateScheduleItemTool());
            $registry->register(new \Platform\Events\Tools\DeleteScheduleItemTool());

            // --- EventNotes (Single-CUD) ---
            $registry->register(new \Platform\Events\Tools\ListEventNotesTool());
            $registry->register(new \Platform\Events\Tools\GetEventNoteTool());
            $registry->register(new \Platform\Events\Tools\CreateEventNoteTool());
            $registry->register(new \Platform\Events\Tools\UpdateEventNoteTool());
            $registry->register(new \Platform\Events\Tools\DeleteEventNoteTool());

            // --- Angebote (Quotes) ---
            $registry->register(new \Platform\Events\Tools\ListQuoteItemsTool());
            $registry->register(new \Platform\Events\Tools\CreateQuoteItemTool());
            $registry->register(new \Platform\Events\Tools\ListQuotePositionsTool());
            $registry->register(new \Platform\Events\Tools\CreateQuotePositionTool());

            // --- Bestellungen (Orders) ---
            $registry->register(new \Platform\Events\Tools\ListOrderItemsTool());
            $registry->register(new \Platform\Events\Tools\CreateOrderItemTool());
            $registry->register(new \Platform\Events\Tools\ListOrderPositionsTool());
            $registry->register(new \Platform\Events\Tools\CreateOrderPositionTool());

            // --- Read-Tools für weitere Entitäten ---
            $registry->register(new \Platform\Events\Tools\ListContractsTool());
            $registry->register(new \Platform\Events\Tools\ListInvoicesTool());
            $registry->register(new \Platform\Events\Tools\ListPickListsTool());
            $registry->register(new \Platform\Events\Tools\ListFeedbackEntriesTool());
            $registry->register(new \Platform\Events\Tools\ListEmailLogsTool());
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

            // Segmentweise kebab-case-Umwandlung: Detail/ProjektFunction → detail.projekt-function
            // (Str::kebab direkt auf den Gesamtpfad wuerde einen Bindestrich zwischen
            // Verzeichnisseparator und folgendem Grossbuchstaben einfuegen.)
            $pathWithoutExt = str_replace('.php', '', $relativePath);
            $segments = preg_split('#[/\\\\]#', $pathWithoutExt);
            $aliasPath = implode('.', array_map(fn ($seg) => Str::kebab($seg), $segments));
            $alias = $prefix . '.' . $aliasPath;

            Livewire::component($alias, $class);
        }
    }
}
