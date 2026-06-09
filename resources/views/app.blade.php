<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>هم‌اندیش</title>
    {{-- Messenger mini-app SDKs. Eitaa's official SDK exposes window.Eitaa.WebApp
         (incl. initData); Bale's exposes window.Bale.WebApp. Each loads only
         inside its own host and is a no-op elsewhere. --}}
    <script src="https://developer.eitaa.com/eitaa-web-app.js"></script>
    <script src="https://tapi.bale.ai/miniapp.js" defer></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50">
    <div id="app"></div>
</body>
</html>
