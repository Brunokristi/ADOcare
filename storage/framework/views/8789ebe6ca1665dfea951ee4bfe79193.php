<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e(config('app.name', 'Laravel')); ?></title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <?php echo app('Illuminate\Foundation\Vite')(['resources/js/app.ts', 'resources/css/app.css']); ?>
</head>

<body class="font-sans antialiased bg-gray-100 text-gray-800">
    <main id="app">

    </main>
</body>

</html>
<?php /**PATH /Users/brunokristian/Documents/ADOcare/resources/views/app.blade.php ENDPATH**/ ?>