<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

class WidgetController extends Controller
{
    public function __invoke(): Response
    {
        $path = public_path('widget.js');

        abort_unless(File::exists($path), 404);

        return response(File::get($path), 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => app()->isProduction()
                ? 'public, max-age=3600'
                : 'no-store, no-cache, must-revalidate',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
