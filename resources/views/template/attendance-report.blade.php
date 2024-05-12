@php @endphp
        <!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>
<body>
<div>
    <span
            style="overflow: hidden; display: inline-block; margin: 0.00px 0.00px; border: 0.00px solid #000000; transform: rotate(0.00rad) translateZ(0px); -webkit-transform: rotate(0.00rad) translateZ(0px); width: 100%; height: 100px;">
        <img
                src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/letter-head.png'))) }}"
                style="height: 100%; margin-left: 0.00px; margin-top: 0.00px; transform: rotate(0.00rad) translateZ(0px); -webkit-transform: rotate(0.00rad) translateZ(0px);"
                title="">
    </span>
</div>
<hr>
<h2>Attendance Report</h2>
<h3>Employee Name: {{$name}}</h3>
<table border="1" style="width: 100%">
    <tr>
        <th>Month / Year</th>
        @foreach(range(1,31) as $key => $day)
            <th>{{$day}}</th>
        @endforeach
    </tr>
    @foreach($attendance as $key => $value)
        <tr>
            <td>{{$key}}</td>
            @foreach($value as $val)
                @php
                    match ($val) {
                        'Y' => $color = 'lightgreen',
                        'PH' => $color = 'LightPink',
                        'NA' => $color = 'OldLace',
                        default => $color = 'LightGray',
                    };
                @endphp
                <td style="background-color: {{$color}}">{{$val}}</td>
            @endforeach
        </tr>

    @endforeach
</table>

{{--Legend--}}
<h4 style="margin-top: 2rem">
    Legend
</h4>
<table border="1" style="width: 50%">
    <thead>
    <th>Codes</th>
    <th>Description</th>
    <th>Description</th>
    <th>Days</th>
    </thead>
    <tbody>
    <tr>
        <td style="background-color: lightgreen">Y</td>
        <td>In Office</td>
        <td></td>
        <td>{{collect($attendance)->map(fn($i)=>collect($i)->filter(fn($j)=>$j == 'Y')->count())->sum()}}</td>
    </tr>
    <tr>
        <td style="background-color: LightPink">PH</td>
        <td>Public Holiday</td>
        <td>Public Holiday Based on Kuala Lumpur</td>
        <td>{{collect($attendance)->map(fn($i)=>collect($i)->filter(fn($j)=>$j == 'PH')->count())->sum()}}</td>
    </tr>
    <tr>
        <td style="background-color: OldLace">NA</td>
        <td>Not Available</td>
        <td>Not in employment period</td>
        <td>-</td>
    </tr>

    <tr>
        <td style="background-color: LightGray">WE</td>
        <td>Weekend</td>
        <td></td>
        <td>-</td>
    </tr>
    </tbody>
</table>


</body>
</html>
