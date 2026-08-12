<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server Side Rendering
    |--------------------------------------------------------------------------
    |
    | These options configures if and how Inertia uses Server Side Rendering
    | to pre-render the initial visits made to your application's pages.
    |
    | You can specify a custom SSR bundle path, or omit it to let Inertia
    | try and automatically detect it for you.
    |
    | Do note that enabling these options will NOT automatically make SSR work,
    | as a separate rendering service needs to be available. To learn more,
    | please visit https://inertiajs.com/server-side-rendering
    |
    */

    'ssr' => [

        'enabled' => (bool) env('INERTIA_SSR_ENABLED', true),

        'runtime' => env('INERTIA_SSR_RUNTIME', 'node'),

        'ensure_runtime_exists' => (bool) env('INERTIA_SSR_ENSURE_RUNTIME_EXISTS', false),

        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),

        'ensure_bundle_exists' => (bool) env('INERTIA_SSR_ENSURE_BUNDLE_EXISTS', true),

        // 'bundle' => base_path('bootstrap/ssr/ssr.mjs'),

        /*
        |--------------------------------------------------------------------------
        | SSR Error Handling
        |--------------------------------------------------------------------------
        |
        | When SSR rendering fails, Inertia gracefully falls back to client-side
        | rendering. Set throw_on_error to true to throw an exception instead.
        | This is useful for E2E testing where you want SSR errors to fail loudly.
        |
        | You can also listen for the Inertia\Ssr\SsrRenderFailed event to handle
        | failures in your own way (e.g., logging, error tracking service).
        |
        */

        'throw_on_error' => (bool) env('INERTIA_SSR_THROW_ON_ERROR', false),

    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | Set `ensure_pages_exist` to true if you want to enforce that Inertia page
    | components exist on disk when rendering a page. This is useful for
    | catching missing or misnamed components.
    |
    | The `paths` and `extensions` options define where to look for page
    | components and which file extensions to consider.
    |
    */

    'pages' => [

        'ensure_pages_exist' => false,

        'paths' => [

            resource_path('js/Pages'),

        ],

        'extensions' => [

            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',

        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | When using `assertInertia`, the assertion attempts to locate the
    | component as a file relative to the `pages.paths` AND with any of
    | the `pages.extensions` specified above.
    |
    | You can disable this behavior by setting `ensure_pages_exist`
    | to false.
    |
    */

    'testing' => [

        'ensure_pages_exist' => true,

        /*
         * DUAS raízes desde 2026-08-12: a tela pode morar no núcleo OU dentro do módulo dono
         * (`Modules/<X>/Resources/js/Pages/**`), e o resolver do Inertia mescla os dois globs
         * em `resources/js/app.tsx` + `ssr.tsx`.
         *
         * Sem declarar aqui, o `page_paths` cai no default do pacote (só `resource_path('js/Pages')`)
         * e o `AssertableInertia::component()` reprova tela que resolve PERFEITAMENTE em runtime —
         * foi o que derrubou 3 asserts do `ForjaRoutesSmokeTest` no PR da migração.
         *
         * O glob é avaliado uma vez no boot da config; módulo novo entra sem editar este arquivo.
         */
        'page_paths' => array_merge(
            [resource_path('js/Pages')],
            glob(base_path('Modules/*/Resources/js/Pages')) ?: [],
        ),

    ],

    /*
    |--------------------------------------------------------------------------
    | Expose Shared Prop Keys
    |--------------------------------------------------------------------------
    |
    | When enabled, each page response includes a `sharedProps` metadata key
    | listing the top-level prop keys that were registered via `Inertia::share`.
    | The frontend can use this to carry shared props over during instant visits.
    |
    */

    'expose_shared_prop_keys' => true,

    /*
    |--------------------------------------------------------------------------
    | History
    |--------------------------------------------------------------------------
    |
    | Enable `encrypt` to encrypt page data before it is stored in the
    | browser's history state, preventing sensitive information from
    | being accessible after logout. Can also be enabled per-request
    | or via the `inertia.encrypt` middleware.
    |
    */

    'history' => [

        'encrypt' => (bool) env('INERTIA_ENCRYPT_HISTORY', false),

    ],

];
