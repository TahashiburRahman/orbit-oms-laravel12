<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Payslip {{ $entry->employee_code }} · {{ $run->period }}</title>
    @include('partials.theme-init')
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .payslip-page{background:var(--canvas);color:var(--ink);padding:32px 24px;min-height:100vh}
        .payslip{max-width:800px;margin:auto;padding:40px;background:var(--surface);border:1px solid var(--line);border-radius:8px}
        .payslip-header{display:flex;justify-content:space-between;gap:24px;border-bottom:1px solid var(--line);padding-bottom:24px;margin-bottom:28px}
        .payslip-header h1{margin-bottom:8px}.payslip-header p{margin-bottom:0}.payslip-period{text-align:right;align-self:flex-end}
        .payslip table{width:100%;margin:28px 0}.payslip th,.payslip td{padding:15px 0;border-bottom:1px solid var(--line)}.payslip td:last-child{text-align:right;font-variant-numeric:tabular-nums}.payslip tbody th{text-transform:none;letter-spacing:0;color:var(--muted);font-weight:500}.payslip .payslip-total th,.payslip .payslip-total td{font-size:18px;color:var(--ink);font-weight:650;border-bottom:0}
        .print-controls{max-width:800px;margin:0 auto 20px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}.payslip-theme{margin-left:auto;display:flex;align-items:center;gap:8px}.payslip-theme select{width:auto;min-height:36px}.payslip-footer{border-top:1px solid var(--line);padding-top:20px;margin-top:24px;font-size:12px}
        @media(max-width:600px){.payslip-page{padding:20px 14px}.payslip{padding:24px 20px}.payslip-header{display:block}.payslip-period{text-align:left;margin-top:16px}.payslip-theme{margin-left:0}}
        @media print{.payslip-page{background:#fff!important;color:#17201c!important;padding:0}.payslip{max-width:none;border:0;border-radius:0;padding:12px;background:#fff!important;color:#17201c!important}.print-controls{display:none!important}.payslip h1,.payslip h2,.payslip strong,.payslip td,.payslip .payslip-total th{color:#17201c!important}.payslip .muted,.payslip tbody th{color:#46564d!important}.payslip th,.payslip td,.payslip-header,.payslip-footer{border-color:#d3dcd6!important}.payslip .grid-form{display:grid;grid-template-columns:1fr 1fr}}
    </style>
</head>
<body class="payslip-page">
<div class="print-controls"><button type="button" class="btn" onclick="window.print()">Print payslip</button><a class="btn-secondary" href="{{ route('payroll.show', $run) }}">Back to payroll</a><label class="payslip-theme">Theme<select data-theme-select aria-label="Theme preference"><option value="light">Light</option><option value="dark">Dark</option><option value="system">System</option></select></label></div>
@php($effective = $entry->effective())
<main class="payslip">
    <header class="payslip-header"><div><p class="eyebrow">Orbit Office · Confidential</p><h1>Salary payslip</h1></div><p class="payslip-period">{{ \Carbon\Carbon::createFromFormat('Y-m', $run->period)->format('F Y') }}</p></header>
    <div class="grid-form"><div><h2>{{ $entry->employee_name }}</h2><p class="muted">Employee code: {{ $entry->employee_code }}</p></div><div><p>Status: <strong>{{ ucfirst($run->status) }}</strong><br>Currency: BDT</p>@if(in_array($run->status, ['draft', 'submitted']))<p class="muted">Provisional · Awaiting approval</p>@endif</div></div>
    <table aria-label="Salary breakdown"><tbody><tr><th scope="row">Base salary</th><td>{{ \App\Support\DecimalMoney::display($effective->base_salary) }}</td></tr><tr><th scope="row">Allowances</th><td>{{ \App\Support\DecimalMoney::display($effective->allowances) }}</td></tr><tr><th scope="row">Deductions</th><td>{{ \App\Support\DecimalMoney::display($effective->deductions) }}</td></tr><tr class="payslip-total"><th scope="row">Net salary</th><td>{{ \App\Support\DecimalMoney::display($effective->net_pay) }}</td></tr></tbody></table>
    @if($run->approved_at)<p>Approved {{ $run->approved_at->format('d M Y, h:i A') }}</p>@endif
    @if($run->paid_at)<p>Payment recorded {{ $run->paid_at->format('d M Y, h:i A') }}</p>@endif
    @if($entry->corrections->isNotEmpty())<p class="muted">Includes {{ $entry->corrections->count() }} authorized {{ $entry->corrections->count() === 1 ? 'correction' : 'corrections' }}. The approved original is preserved in payroll history.</p>@endif
    <footer class="payslip-footer muted"><p>Generated {{ now()->format('d M Y, h:i A') }} (Asia/Dhaka).</p><p>This record does not initiate a salary transfer.</p></footer>
</main>
</body>
</html>
