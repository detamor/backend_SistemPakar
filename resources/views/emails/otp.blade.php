<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kode OTP - System Pakar</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f0fdf4; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 480px; margin: 40px auto; background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); overflow: hidden;">
        <!-- Header -->
        <tr>
            <td style="background: linear-gradient(135deg, #16a34a, #15803d); padding: 32px 24px; text-align: center;">
                <h1 style="color: #ffffff; font-size: 22px; margin: 0; font-weight: 700;">
                    🌿 System Pakar
                </h1>
                <p style="color: #bbf7d0; font-size: 14px; margin: 8px 0 0;">
                    Sistem Pakar Diagnosis Tanaman Hias
                </p>
            </td>
        </tr>

        <!-- Content -->
        <tr>
            <td style="padding: 32px 24px;">
                <h2 style="color: #1f2937; font-size: 18px; margin: 0 0 12px; font-weight: 600;">
                    @if($type === 'registration')
                        Verifikasi Akun Anda
                    @else
                        Reset Password
                    @endif
                </h2>

                <p style="color: #6b7280; font-size: 14px; line-height: 1.6; margin: 0 0 24px;">
                    @if($type === 'registration')
                        Terima kasih telah mendaftar di System Pakar! Gunakan kode OTP berikut untuk menyelesaikan proses registrasi:
                    @else
                        Anda telah meminta reset password. Gunakan kode OTP berikut untuk mengatur ulang password Anda:
                    @endif
                </p>

                <!-- OTP Code -->
                <div style="background-color: #f0fdf4; border: 2px dashed #16a34a; border-radius: 12px; padding: 20px; text-align: center; margin: 0 0 24px;">
                    <p style="color: #6b7280; font-size: 12px; margin: 0 0 8px; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;">
                        Kode OTP Anda
                    </p>
                    <p style="color: #16a34a; font-size: 36px; font-weight: 800; letter-spacing: 8px; margin: 0; font-family: 'Courier New', monospace;">
                        {{ $otpCode }}
                    </p>
                </div>

                <!-- Warning -->
                <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 0 8px 8px 0; padding: 12px 16px; margin: 0 0 24px;">
                    <p style="color: #92400e; font-size: 13px; margin: 0; line-height: 1.5;">
                        ⏱️ Kode ini berlaku selama <strong>10 menit</strong>.<br>
                        🔒 Jangan bagikan kode ini kepada siapapun.
                    </p>
                </div>

                <p style="color: #9ca3af; font-size: 13px; line-height: 1.5; margin: 0;">
                    Jika Anda tidak merasa melakukan permintaan ini, abaikan email ini.
                </p>
            </td>
        </tr>

        <!-- Footer -->
        <tr>
            <td style="background-color: #f9fafb; padding: 20px 24px; text-align: center; border-top: 1px solid #e5e7eb;">
                <p style="color: #9ca3af; font-size: 12px; margin: 0;">
                    &copy; {{ date('Y') }} System Pakar — Diagnosis Tanaman Hias
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
