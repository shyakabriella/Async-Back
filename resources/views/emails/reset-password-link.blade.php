<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Your Password</title>
</head>
<body style="margin:0; padding:0; background-color:#0b1020; font-family:Arial, Helvetica, sans-serif; color:#ffffff;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#0b1020; padding:30px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px; background:#11182d; border-radius:18px; overflow:hidden; border:1px solid rgba(255,255,255,0.08);">
                    <tr>
                        <td style="padding:32px; background:linear-gradient(135deg, rgba(96,80,240,0.22), rgba(17,24,45,1));">
                            <p style="margin:0; font-size:12px; letter-spacing:2px; text-transform:uppercase; color:#c9c3ff; font-weight:bold;">
                                {{ $appName }}
                            </p>

                            <h1 style="margin:14px 0 0; font-size:28px; line-height:1.3; color:#ffffff;">
                                Reset Your Password
                            </h1>

                            <p style="margin:14px 0 0; font-size:15px; line-height:1.8; color:#d7dbef;">
                                Hello {{ $user->name ?? 'User' }},
                            </p>

                            <p style="margin:14px 0 0; font-size:15px; line-height:1.8; color:#d7dbef;">
                                We received a request to reset your password. Click the button below to open the secure password reset page.
                            </p>

                            <div style="margin:28px 0;">
                                <a href="{{ $resetUrl }}"
                                   style="display:inline-block; background-color:#6050F0; color:#ffffff; text-decoration:none; font-size:15px; font-weight:bold; padding:14px 24px; border-radius:12px;">
                                    Reset My Password
                                </a>
                            </div>

                            <p style="margin:0; font-size:14px; line-height:1.8; color:#d7dbef;">
                                If the button does not work, copy and paste this link into your browser:
                            </p>

                            <p style="margin:12px 0 0; font-size:13px; line-height:1.8; color:#b7c0e0; word-break:break-all;">
                                {{ $resetUrl }}
                            </p>

                            <p style="margin:24px 0 0; font-size:14px; line-height:1.8; color:#d7dbef;">
                                If you did not request a password reset, you can safely ignore this email.
                            </p>

                            <p style="margin:24px 0 0; font-size:14px; line-height:1.8; color:#d7dbef;">
                                Thank you,<br>
                                <strong>{{ $appName }} Team</strong>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 32px; background:#0d1428; border-top:1px solid rgba(255,255,255,0.06);">
                            <p style="margin:0; font-size:12px; line-height:1.7; color:#99a3c7;">
                                This password reset email was sent from {{ $appName }}.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>