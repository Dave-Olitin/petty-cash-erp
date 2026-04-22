<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<style>
body {
    background-color: #f3f4f6;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    color: #374151;
    margin: 0;
    padding: 0;
    -webkit-text-size-adjust: none;
}
.wrapper {
    background-color: #f3f4f6;
    margin: 0;
    padding: 40px 20px;
    width: 100%;
}
.content {
    background-color: #ffffff;
    max-width: 600px;
    margin: 0 auto;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}
.header {
    background-color: #1e3a8a;
    padding: 30px;
    text-align: center;
    color: #ffffff;
}
.header h1 {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
    letter-spacing: 0.5px;
}
.header p {
    margin: 5px 0 0 0;
    font-size: 14px;
    opacity: 0.9;
}
.body {
    padding: 40px 30px;
}
.greeting {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 20px;
}
.intro {
    font-size: 15px;
    line-height: 1.6;
    margin-bottom: 30px;
    color: #4b5563;
}
.details-box {
    background-color: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
}
.details-box table {
    width: 100%;
    border-collapse: collapse;
}
.details-box th {
    text-align: left;
    padding: 10px 0;
    color: #6b7280;
    font-size: 13px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #e5e7eb;
    width: 35%;
}
.details-box td {
    text-align: left;
    padding: 10px 0;
    color: #111827;
    font-size: 15px;
    font-weight: 600;
    border-bottom: 1px solid #e5e7eb;
}
.details-box tr:last-child th,
.details-box tr:last-child td {
    border-bottom: none;
    padding-bottom: 0;
}
.comments-box {
    background-color: #fef2f2;
    border-left: 4px solid #ef4444;
    padding: 15px 20px;
    margin-bottom: 30px;
    border-radius: 0 8px 8px 0;
}
.comments-title {
    color: #991b1b;
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    margin-bottom: 5px;
}
.comments-text {
    color: #7f1d1d;
    font-size: 14px;
    margin: 0;
}
.action {
    text-align: center;
    margin: 40px 0;
}
.btn {
    background-color: #2563eb;
    color: #ffffff;
    text-decoration: none;
    padding: 12px 30px;
    border-radius: 6px;
    font-size: 16px;
    font-weight: 500;
    display: inline-block;
    transition: background-color 0.2s;
}
.btn:hover {
    background-color: #1d4ed8;
}
.footer {
    text-align: center;
    padding: 30px;
    color: #9ca3af;
    font-size: 13px;
    border-top: 1px solid #f3f4f6;
}
.amount {
    color: #059669;
}
</style>
</head>
<body>
<div class="wrapper">
    <div class="content">
        <div class="header">
            <h1>Erick Trading Co.</h1>
            <p>Petty Cash & Voucher System</p>
        </div>
        
        <div class="body">
            <div class="greeting">Hello, {{ $user->name }}!</div>
            <div class="intro">{{ $intro }}</div>
            
            <div class="details-box">
                <table>
                    <tr>
                        <th>Voucher No.</th>
                        <td>{{ $voucher->voucher_number }}</td>
                    </tr>
                    <tr>
                        <th>Payee</th>
                        <td>{{ $voucher->payee }}</td>
                    </tr>
                    <tr>
                        <th>Amount</th>
                        <td class="amount">AED {{ number_format($voucher->amount, 2) }}</td>
                    </tr>
                    <tr>
                        <th>Requester</th>
                        <td>{{ $voucher->user->name }}</td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            @php
                                $statusColors = [
                                    'draft' => '#6b7280',
                                    'pending_checker' => '#d97706',
                                    'pending_approver' => '#d97706',
                                    'approved' => '#059669',
                                    'rejected' => '#dc2626',
                                    'paid' => '#059669',
                                ];
                                $color = $statusColors[$voucher->status] ?? '#374151';
                            @endphp
                            <span style="color: {{ $color }}; text-transform: capitalize;">
                                {{ str_replace('_', ' ', $voucher->status) }}
                            </span>
                        </td>
                    </tr>
                </table>
            </div>

            @if(!empty($comments))
            <div class="comments-box">
                <div class="comments-title">Comments / Feedback</div>
                <p class="comments-text">{{ $comments }}</p>
            </div>
            @endif

            <div class="action">
                <a href="{{ url('/vouchers/vouchers/' . $voucher->id) }}" class="btn" style="color: #ffffff;">View Voucher</a>
            </div>
        </div>
        
        <div class="footer">
            &copy; {{ date('Y') }} Erick Trading Co. All rights reserved.<br>
            This is an automated notification. Please do not reply to this email.
        </div>
    </div>
</div>
</body>
</html>
