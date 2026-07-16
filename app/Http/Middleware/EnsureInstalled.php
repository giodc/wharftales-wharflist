<?php

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstalled
{
    public function __construct(protected SettingsService $settings) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->settings->isInstalled()) {
            if ($request->routeIs('setup*')) {
                return redirect()->route('dashboard');
            }

            return $next($request);
        }

        if ($this->allowsWhileInstalling($request)) {
            return $next($request);
        }

        return redirect()->route('setup');
    }

    protected function allowsWhileInstalling(Request $request): bool
    {
        if ($request->routeIs('setup*') || $request->is('up')) {
            return true;
        }

        // Public widget / API / demo must stay reachable before & after install.
        if ($request->routeIs('widget', 'widget.demo', 'api.subscribe', 'api.php', 'verify', 'unsubscribe.*')) {
            return true;
        }

        if ($request->is('widget.js', 'api.php', 'api/subscribe', 'widget-demo', 'verify/*', 'unsubscribe')) {
            return true;
        }

        // Livewire updates for the setup wizard post here — must not redirect.
        if ($request->is('livewire/*') || $request->is('livewire-*/*')) {
            return true;
        }

        // Flux / Livewire assets needed to render the setup UI.
        if ($request->is('flux/*')) {
            return true;
        }

        return false;
    }
}
