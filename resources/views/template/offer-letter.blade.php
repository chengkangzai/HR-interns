<html>
<head>
    <meta content="text/html; charset=UTF-8" http-equiv="content-type">
    <style type="text/css">
        html {
            margin-top: 15px;
            margin-bottom: 5px;
        }

        ol {
            margin: 0;
            padding: 0
        }

        table td, table th {
            padding: 0
        }

        .c1 {
            color: #000000;
            font-weight: 400;
            text-decoration: none;
            vertical-align: baseline;
            font-size: 11pt;
            font-family: "Arial";
            font-style: normal
        }

        .c0 {
            padding-top: 0pt;
            padding-bottom: 0pt;
            line-height: 1.15;
            orphans: 2;
            widows: 2;
            text-align: left
        }

        .c3 {
            padding-top: 0pt;
            padding-bottom: 0pt;
            line-height: 1.15;
            orphans: 2;
            widows: 2;
            text-align: justify
        }

        .c2 {
            padding-top: 0pt;
            padding-bottom: 0pt;
            line-height: 1.0;
            orphans: 2;
            widows: 2;
            text-align: left
        }

        .c5 {
            text-decoration-skip-ink: none;
            -webkit-text-decoration-skip: none;
            color: #1155cc;
            text-decoration: underline
        }

        .c9 {
            background-color: #ffffff;
            max-width: 451.4pt;
            padding: 0pt 30pt 30pt;
        }

        .c7 {
            color: inherit;
            text-decoration: inherit
        }

        .c8 {
            background-color: #ffffff;
            color: #222222
        }

        .c4 {
            height: 8pt
        }

        .c6 {
            font-weight: 700
        }

        .title {
            padding-top: 0pt;
            color: #000000;
            font-size: 26pt;
            padding-bottom: 3pt;
            font-family: "Arial";
            line-height: 1.15;
            page-break-after: avoid;
            orphans: 2;
            widows: 2;
            text-align: left
        }

        .subtitle {
            padding-top: 0pt;
            color: #666666;
            font-size: 15pt;
            padding-bottom: 16pt;
            font-family: "Arial";
            line-height: 1.15;
            page-break-after: avoid;
            orphans: 2;
            widows: 2;
            text-align: left
        }

        li {
            color: #000000;
            font-size: 11pt;
            font-family: "Arial"
        }

        p {
            margin: 0;
            color: #000000;
            font-size: 11pt;
            font-family: "Arial"
        }

        h1 {
            padding-top: 20pt;
            color: #000000;
            font-size: 20pt;
            padding-bottom: 6pt;
            font-family: "Arial";
            line-height: 1.15;
            page-break-after: avoid;
            orphans: 2;
            widows: 2;
            text-align: left
        }

        h2 {
            padding-top: 18pt;
            color: #000000;
            font-size: 16pt;
            padding-bottom: 6pt;
            font-family: "Arial";
            line-height: 1.15;
            page-break-after: avoid;
            orphans: 2;
            widows: 2;
            text-align: left
        }

        h3 {
            padding-top: 16pt;
            color: #434343;
            font-size: 14pt;
            padding-bottom: 4pt;
            font-family: "Arial";
            line-height: 1.15;
            page-break-after: avoid;
            orphans: 2;
            widows: 2;
            text-align: left
        }

        h4 {
            padding-top: 14pt;
            color: #666666;
            font-size: 12pt;
            padding-bottom: 4pt;
            font-family: "Arial";
            line-height: 1.15;
            page-break-after: avoid;
            orphans: 2;
            widows: 2;
            text-align: left
        }

        h5 {
            padding-top: 12pt;
            color: #666666;
            font-size: 11pt;
            padding-bottom: 4pt;
            font-family: "Arial";
            line-height: 1.15;
            page-break-after: avoid;
            orphans: 2;
            widows: 2;
            text-align: left
        }

        h6 {
            padding-top: 12pt;
            color: #666666;
            font-size: 11pt;
            padding-bottom: 4pt;
            font-family: "Arial";
            line-height: 1.15;
            page-break-after: avoid;
            font-style: italic;
            orphans: 2;
            widows: 2;
            text-align: left
        }</style>
</head>
<body class="c9">
<div><p class="c2">

        <img
            src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/letter-head.png'))) }}"
            style="width: 600.50px; margin-left: 0.00px; margin-top: 0.00px; transform: rotate(0.00rad) translateZ(0px); -webkit-transform: rotate(0.00rad) translateZ(0px);"
            title="">
    </p>
</div>
<hr>
<p class="c0"><span class="c1">{{now()->format('d F Y')}}</span></p>
<p class="c0"><span class="c1">Subject: Internship Offer - {{ $position->title }} Intern</span></p>
<p class="c0 c4"><span class="c1"></span></p>
<p class="c0"><span class="c1">Dear {{ $candidate->name }},</span></p>
<p class="c0 c4"><span class="c1"></span></p>
<p class="c3"><span class="c1">I am delighted to extend an offer to you for the {{ $position->title }} Internship position at Pixalink. </span>
</p>
<p class="c3 c4"><span class="c1"></span></p>
<p class="c3"><span class="c1">The terms of your internship are as follows:</span></p>
<p class="c3 c4"><span class="c1"></span></p>
<p class="c3"><span class="c6">Start date:</span><span>&nbsp;Your internship will commence on </span><span
        class="c8">{{ $candidate->from->format('d F Y') }}</span><span
        class="c1">&nbsp;and is expected to last until {{ $candidate->to->format('d F Y') }}. ({{ ceil($candidate->from->floatDiffInWeeks($candidate->to)) }} weeks)</span>
</p>
<p class="c3 c4"><span class="c1"></span></p>
<p class="c3">
    <span class="c6">Working hours:</span>
    <span class="c1">&nbsp;Your working schedule will be from {{$schedule['from']->format('g:iA')}} to {{$schedule['to']->format('g:iA')}}, 5 Days a week</span>
</p>
<p class="c3 c4"><span class="c1"></span></p>
<p class="c3"><span
        class="c6">Stipend: </span><span>{{ intval($pay) == 0 ? 'No allowance' : 'RM'.number_format($pay, 2)}}</span>
</p>
<p class="c3 c4"><span class="c1"></span></p>
<p class="c3"><span class="c6">Location:</span><span class="c1">&nbsp;The internship will be based at our office located at ASBX Room, Level 7, Asia Pacific University of Technology &amp; Innovation (APU), Jalan Teknologi 5, Taman Teknologi Malaysia, 57000 Kuala Lumpur, Wilayah Persekutuan Kuala Lumpur. </span>
</p>
<p class="c3 c4"><span class="c1"></span></p>
<p class="c3"><span class="c1">However, our company is set to work at the office 3 days a week which is Wednesday to Friday. You can choose to work from home on Monday and Tuesday.</span>
</p>
<p class="c3 c4"><span class="c1"></span></p>
<p class="c3"><span class="c6">Reporting to:</span><span class="c1">&nbsp;You will be reporting directly to the assigned Supervisor (upon reporting to work)</span>
</p>
<p class="c3 c4"><span class="c1"></span></p>
<p class="c3 c4"><span class="c1"></span></p>
<p class="c3"><span class="c6">Confidentiality Agreement:</span><span class="c1">&nbsp;Please be reminded that all interns are expected to sign a confidentiality agreement to protect the company&#39;s proprietary information.</span>
</p>
<p class="c3 c4"><span class="c1"></span></p>
<p class="c3"><span class="c1">To confirm your acceptance of this offer, please sign and return a copy of this letter. If you have any questions or require additional information, please feel free to contact me directly at +60168390138.</span>
</p>
<p class="c3 c4"><span class="c1"></span></p>
<p class="c3"><span class="c1">We look forward to having you as part of our team and are excited about the potential you bring. We are confident that this internship will be a mutually beneficial experience.</span>
</p>
<p class="c0 c4"><span class="c1"></span></p>
<table>
    <td>
        <p class="c0"><span class="c1">Best regards,</span></p>
        <p class="c0 c4"><span class="c1"></span></p>
        <img
            src="data:image/png;base64,{{ base64_encode(file_get_contents(storage_path('signature.jpeg'))) }}"
            style="height: 80px; margin-left: 0.00px; margin-top: 0.00px; transform: rotate(0.00rad) translateZ(0px); -webkit-transform: rotate(0.00rad) translateZ(0px);"
            title="">
        <p class="c0"><span class="c1">Eddie Chong</span></p>
        <p class="c0"><span class="c1">Operation Manager</span></p>
        <p class="c0"><span class="c1">+60168390138</span></p>
    </td>
    <td>
        <img
            src="data:image/png;base64,{{ base64_encode(file_get_contents(storage_path('Company-Stamp-For-Office-Use-Only.png'))) }}"
            style="height: 120px; margin-left: 10.00px; margin-top: 0.00px; transform: rotate(0.00rad) translateZ(0px); -webkit-transform: rotate(0.00rad) translateZ(0px);"
            title="">
    </td>
</table>
<p class="c0 c4"><span class="c1"></span></p>
<p class="c0"><span class="c1">I, ____________________________, accepted the internship offer for the {{ $position->title }} Internship position at Pixalink Sdn. Bhd.</span>
</p>
<p class="c0 c4"><span class="c1"></span></p>
<p class="c0 c4"><span class="c1"></span></p>
<p class="c0 c4"><span class="c1"></span></p>
<p class="c0 c4"><span class="c1"></span></p>
<p class="c0"><span class="c1">___________________________</span></p>
<p class="c0"><span class="c1">[Intern&#39;s Signature]</span></p>
<p class="c0"><span class="c1">Name:</span></p>
<p class="c0"><span class="c1">IC:</span></p>
<p class="c0"><span class="c1">Date:</span></p>
</body>
</html>
