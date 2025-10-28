<!DOCTYPE html>
<html>

<head>
    <title>Password Reset Request</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #3490dc;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }

        .footer {
            margin-top: 30px;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Password Reset Request</h2>
        <p>Hello,</p>
        <p>We received a request to reset your password. Click the button below to proceed:</p>

        <p>
            <a href="{{ $resetLink }}" class="button">Reset Password</a>
        </p>

        <p>This link will expire on {{ $expiresAt }} (UTC) or after 30 minutes.</p>

        <p>If you didn't request a password reset, please ignore this email or contact our support team if you have
            concerns.</p>

        <div class="footer">
            <p>Thank you,</p>
            <p>Your Application Team</p>
        </div>
    </div>
</body>

</html>
