<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f6f6f6;
            margin: 0;
            padding: 0;
        }

        .email-wrapper {
            width: 100%;
            background-color: #f6f6f6;
            padding: 20px 0;
        }

        .email-content {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .1);
        }

        .email-body {
            padding: 20px;
            color: #333;
            line-height: 1.4;
        }

        .email-footer {
            padding: 20px;
            font-size: 12px;
            color: #999;
            text-align: center;
        }

        a {
            color: #1a73e8;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <div class="email-wrapper">
        <div class="email-content">
            @include('emails.partials.header')
            <div class="email-body">
                @yield('content')
            </div>
            @include('emails.partials.footer')
        </div>
    </div>
</body>

</html>
