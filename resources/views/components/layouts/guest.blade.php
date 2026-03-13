<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Te invitamos a nuestra boda' }}</title>
    
    <!-- Premium Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Inter:wght@300;400;600&family=Great+Vibes&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
    
    <style>
        .font-serif-premium { font-family: 'Playfair Display', serif; }
        .font-handwritten { font-family: 'Great Vibes', cursive; }
        
        [x-cloak] { display: none !important; }
        
        .fade-up {
            animation: fadeUp 1.2s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
        }
        
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .bg-invitation {
            background: radial-gradient(circle at top, #fdfbf7 0%, #f5f2ed 100%);
        }
    </style>
</head>
<body class="min-h-screen bg-invitation font-sans text-stone-900 overflow-x-hidden selection:bg-sage-100 selection:text-sage-900">
    <main class="w-full">
        {{ $slot }}
    </main>

    @fluxScripts
</body>
</html>
