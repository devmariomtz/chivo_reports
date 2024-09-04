<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Chivo Reports') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Styles -->
    @livewireStyles
</head>

<body>
    <div class="font-sans text-gray-900 antialiased">
        {{ $slot }}
    </div>

    {{-- footer --}}
    <footer class="bg-white border-t border-gray-200">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <p class="text-center text-sm">
                <span class="text-gray-600">&copy; 2024 Chivo Reports. All rights reserved.</span><br>
                Developed by <a href="https://www.linkedin.com/in/mariomtzdev/" target="_blank"
                    class="text-blue-500 underline">Mario Martínez</a> & <a
                    href="https://www.linkedin.com/in/elizabeth-aguilar-1504dead2/" target="_blank"
                    class="text-blue-500 underline">Elizabeth Aguilar</a>
            </p>
        </div>
    </footer>
    @livewireScripts
</body>

</html>
