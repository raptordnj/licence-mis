<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'Licence MIS') }} Admin</title>
        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        @vite(['resources/css/app.css', 'resources/js/admin/main.ts'])
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>
