{{-- <div id="app">
    <example-component></example-component>
</div>

OLD
@vite('resources/js/app.js') --}}

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Asisstant</title>
    <meta name="csrf-token" content="{{ csrf_token() }}"> 
    {{-- @vite('resources/js/css/main.css') --}}
@vite('resources/js/main.js')
</head>
<body>
    {{-- <div id="app">
        <login-component></login-component>
    </div> --}}
        <div id="app"></div>


    {{-- @vite('resources/js/main.js') --}}
</body>
</html>