<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $data['subject'] ?? 'OTP' }}</title>
</head>
<body style="margin:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7fb;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:16px;border:1px solid #e5e7eb;overflow:hidden;">
                    <tr>
                        <td style="padding:28px 32px 18px;">
                            <h1 style="margin:0;font-size:24px;line-height:1.3;color:#111827;">{{ $data['subject'] ?? 'OTP Verification' }}</h1>
                            <p style="margin:12px 0 0;font-size:15px;line-height:1.7;color:#64748b;">
                                Hi {{ $data['name'] ?? 'there' }}, use the OTP below to continue.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 24px;">
                            <div style="background:#eef4ff;border:1px solid #dbeafe;border-radius:14px;padding:22px;text-align:center;font-size:18px;line-height:1.6;color:#1d4ed8;">
                                {!! $data['text'] ?? '' !!}
                            </div>
                            <p style="margin:18px 0 0;font-size:13px;line-height:1.6;color:#94a3b8;">
                                If you did not request this code, you can safely ignore this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
