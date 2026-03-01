<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Session Expired</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen text-gray-800 font-sans antialiased">
    <div class="max-w-md w-full p-8 bg-white rounded-2xl shadow-xl text-center mx-4">
        <div class="w-16 h-16 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
        <h1 class="text-3xl font-bold mb-2 text-gray-900">Session Expired</h1>
        <p class="text-gray-500 mb-8 leading-relaxed">For your security, your session has timed out due to your browser being idle. Don't worry, just click the button below to resume!</p>
        <button onclick="window.location.reload()" class="w-full inline-flex justify-center items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition-colors shadow-md">
            Refresh & Continue
        </button>
    </div>
</body>
</html>
