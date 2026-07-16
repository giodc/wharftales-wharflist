<?php

use Checkpoint\Checks;

return [

    'checks' => [
        Checks\ComposerAuditCheck::class => true,
        Checks\NpmAuditCheck::class => true,
        Checks\EnvironmentCheck::class => true,
        Checks\GitIgnoreCheck::class => true,
        Checks\FilePermissionsCheck::class => true,
        Checks\HardcodedSecretsCheck::class => true,
        Checks\SqlInjectionCheck::class => true,
        Checks\MassAssignmentCheck::class => true,
        Checks\XssCheck::class => true,
        Checks\CsrfCheck::class => true,
        Checks\OpenRedirectCheck::class => true,
        Checks\CommandInjectionCheck::class => true,
        Checks\InsecureDeserializationCheck::class => true,
        Checks\DebugFunctionsCheck::class => true,
        Checks\SensitiveExposureCheck::class => true,
        Checks\SsrfCheck::class => true,
        Checks\TlsVerificationCheck::class => true,
        Checks\CorsConfigCheck::class => true,
        Checks\PackageFreshnessCheck::class => true,
        Checks\SuspiciousVendorAutoloadCheck::class => true,
        Checks\SupplyChainToolingCheck::class => true,
        Checks\PathTraversalCheck::class => true,
        Checks\WeakCryptographyCheck::class => true,
        Checks\InsecureRngCheck::class => true,
        Checks\SessionSecurityCheck::class => true,
        Checks\EolVersionCheck::class => true,
    ],

    'package_freshness' => [
        'minimum_age_days' => 3,
        'whitelist' => [
            'andreapollastri/checkpoint',
            // Fresh Laravel 13 stack pins — intentional local/prod lockfile.
            'guzzlehttp/guzzle',
            'guzzlehttp/psr7',
            'laravel/framework',
            'league/commonmark',
            'symfony/html-sanitizer',
        ],
    ],

    'suspicious_autoload' => [
        'whitelist' => [
            'laravel/agent-detector',
            'laravel/pao',
            'laravel/prompts',
            'league/csv',
            'livewire/livewire',
            'mockery/mockery',
            'myclabs/deep-copy',
            'nunomaduro/collision',
            'nunomaduro/termwind',
            'phpstan/phpstan',
            'phpunit/phpunit',
            'pragmarx/google2fa',
            'psy/psysh',
            'ralouphie/getallheaders',
            'sebastian/global-state',
            'sebastian/type',
            'symfony/clock',
            'symfony/string',
            'symfony/translation',
            'symfony/var-dumper',
        ],
    ],

    /*
    | Intentional unescaped Blade output after review:
    | - campaign/compose HTML is sanitized via App\Support\Html
    | - 2FA QR SVG is generated server-side by Fortify/Google2FA
    */
    'suppressed' => [
        // resources/views/pages/settings/⚡two-factor-setup-modal.blade.php — QR SVG
        '03765cdd8452',
    ],

    'exclude_paths' => [
        //
    ],

];
