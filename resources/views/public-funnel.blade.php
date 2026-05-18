<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @if (! empty($grapesBuiltPage))
            <link id="grapes-fonts-preconnect-1" rel="preconnect" href="https://fonts.googleapis.com">
            <link id="grapes-fonts-preconnect-2" rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link id="grapes-page-fonts" rel="stylesheet" href="{{ $grapesFontsUrl }}">
            <style id="grapes-page-reset">
                html:has(.grapes-optin-page),
                html:has(.grapes-optin-page) body,
                #app:has(.grapes-optin-page) {
                    margin: 0 !important;
                    padding: 0 !important;
                    width: 100% !important;
                    background: transparent !important;
                }
            </style>
            @if (! empty($grapesPageCss))
                <style id="grapes-page-css">{!! $grapesPageCss !!}</style>
            @endif
        @endif

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        @if (! empty($grapesBuiltPage))
            <div id="grapes-page-boot-loader" aria-busy="true" aria-label="Loading page" style="position:fixed;inset:0;z-index:99999;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1.25rem;background:#f1f5f9;padding:1.5rem;">
                <div style="width:min(28rem,100%);display:flex;flex-direction:column;gap:0.75rem;">
                    <div style="height:2.5rem;width:75%;border-radius:0.5rem;background:linear-gradient(90deg,#e2e8f0 25%,#f8fafc 50%,#e2e8f0 75%);background-size:200% 100%;animation:grapes-boot-shimmer 1.2s ease-in-out infinite;"></div>
                    <div style="height:1rem;width:100%;border-radius:0.375rem;background:linear-gradient(90deg,#e2e8f0 25%,#f8fafc 50%,#e2e8f0 75%);background-size:200% 100%;animation:grapes-boot-shimmer 1.2s ease-in-out infinite 0.1s;"></div>
                    <div style="height:1rem;width:88%;border-radius:0.375rem;background:linear-gradient(90deg,#e2e8f0 25%,#f8fafc 50%,#e2e8f0 75%);background-size:200% 100%;animation:grapes-boot-shimmer 1.2s ease-in-out infinite 0.2s;"></div>
                    <div style="margin-top:0.5rem;height:2.75rem;width:100%;border-radius:0.5rem;background:linear-gradient(90deg,#cbd5e1 25%,#e2e8f0 50%,#cbd5e1 75%);background-size:200% 100%;animation:grapes-boot-shimmer 1.2s ease-in-out infinite 0.15s;"></div>
                </div>
            </div>
            <style>
                @keyframes grapes-boot-shimmer {
                    0% { background-position: 200% 0; }
                    100% { background-position: -200% 0; }
                }
                #grapes-page-boot-loader[hidden] {
                    display: none !important;
                }
            </style>
        @endif

        <x-inertia::app />
    </body>
</html>
