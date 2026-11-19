<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Your password reset code</title></head>
<body style="margin:0;padding:28px 16px;background:#f7f8fc;color:#253047;font-family:Arial,sans-serif;line-height:1.7">
    <main style="max-width:480px;margin:0 auto;padding:32px;background:#fff;border:1px solid #e9edf4;border-radius:12px">
        <p style="margin:0 0 24px;font-size:20px;font-weight:bold;color:#6256e8">Orbit Office</p>
        <h1 style="font-size:23px;line-height:1.3;margin:0 0 16px">Your password reset code</h1>
        <p>Use this code to choose a new password for your Office Management System account:</p>
        <p style="margin:24px 0;padding:18px;background:#f0edff;border-radius:8px;text-align:center;font-family:monospace;font-size:32px;font-weight:bold;letter-spacing:8px;color:#4435aa">{{ $code }}</p>
        <p>This code expires in {{ $expiresMinutes }} minutes. Requesting a new code replaces the previous one.</p>
        <p style="font-size:13px;color:#67748a">If you did not request a password reset, you can ignore this email. Do not share this code with anyone.</p>
    </main>
</body>
</html>
