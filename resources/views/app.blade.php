<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Ajaia Docs: write, import and share documents with your team.">
    <title>{{ config('app.name') }}</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' fill='%233d5b7c'/%3E%3Cpath d='M9 7h10l5 5v13H9z' fill='none' stroke='%23fff' stroke-width='2'/%3E%3C/svg%3E">
    <meta name="realtime" content="{{ json_encode(config('services.realtime.enabled') ? config('services.realtime') : ['enabled' => false]) }}">
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
