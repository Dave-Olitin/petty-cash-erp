<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - Erick Trading Petty Cash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen p-6 text-gray-800">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-2xl overflow-hidden text-center border border-gray-100">
        <div class="bg-gradient-to-br from-blue-600 to-indigo-700 py-12 relative overflow-hidden">
            <!-- Decorative UI Circles -->
            <div class="absolute -right-6 -top-6 w-32 h-32 bg-white opacity-10 rounded-full"></div>
            <div class="absolute -left-10 -bottom-10 w-40 h-40 bg-white opacity-10 rounded-full"></div>
            
            <h1 class="text-7xl font-extrabold text-white relative z-10 drop-shadow-md">@yield('code')</h1>
        </div>
        <div class="p-8">
            <h2 class="text-2xl font-bold mb-3 text-gray-800">@yield('message')</h2>
            
            <p class="text-gray-500 mb-8 leading-relaxed text-sm">
                @if(View::hasSection('details'))
                    @yield('details')
                @else
                    An error occurred while processing your request. Please verify the URL or try again later.
                @endif
            </p>

            <div class="space-y-3">
                <a href="{{ url('/') }}" class="inline-block w-full px-6 py-3.5 bg-blue-600 text-white font-medium rounded-xl hover:bg-blue-700 transition duration-200 shadow-sm hover:shadow">
                    Return to Dashboard
                </a>
                <button onclick="window.history.back()" class="inline-block w-full px-6 py-3.5 bg-gray-100 text-gray-700 font-medium rounded-xl hover:bg-gray-200 transition duration-200">
                    Go Back
                </button>
            </div>
        </div>
    </div>
</body>
</html>
