@php
/** @var \App\Models\Candidate $candidate */
/** @var \App\Models\Position $position */
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>

    <style>
        html,body{
            margin: 60px 0 0;
            padding: 0;
        }
        .text-7xl {
            font-size: 4.5rem; /* 72px */
            line-height: 1;
        }

        .text-2xl{
            font-size: 1.5rem; /* 24px */
            line-height: 2rem; /* 32px */
        }

        .text-3xl{
            font-size: 1.875rem; /* 30px */
            line-height: 2.25rem; /* 36px */
        }

        .mx-auto {
            margin-left: auto;
            margin-right: auto;
        }

        .w-full {
            width: 100%;
        }
    </style>
</head>
<body>

<img
    src="data:image/png;base64,{{ base64_encode(file_get_contents(storage_path('Internship-Certification-bg.png'))) }}"
    style="position: absolute;"
    alt="">
<div class="mx-auto w-full" style="position: absolute; color: black; text-align: center; margin-top: 400px">
    <p class="text-3xl w-full text-center" style="margin-bottom: 10px;">
        {{$candidate->name}}
    </p>
    <span class="">
        As {{$candidate->position->title}}
    </span>
    <br>
    <p class=""  style="margin-top: 10px;">
        From {{$candidate->from->format('d F Y')}} To {{$candidate->to->format('d F Y')}}
    </p>
</div>
</body>
</html>
