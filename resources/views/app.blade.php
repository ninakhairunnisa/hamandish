<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>هم‌اندیش</title>
    {{-- Messenger mini-app SDKs (Bale / Eitaa). Loaded if present in the host. --}}
    <script src="https://tapi.bale.ai/miniapp.js" defer></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50">
    <div id="app"></div>
</body>
</html>
