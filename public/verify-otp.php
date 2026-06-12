<?php
session_start();

// Check if email is in session (came from registration)
if (!isset($_SESSION['pending_verification_email'])) {
    header('Location: /public/signup.php');
    exit;
}

$email = $_SESSION['pending_verification_email'];
$name = $_SESSION['pending_verification_name'] ?? 'User';

// ✅ PRESERVE REDIRECT URL + SAVE IN SESSION
$redirectUrl = $_GET['redirect'] ?? '';
$_SESSION['post_register_redirect'] = $redirectUrl; // ✅ FIXED: auth.php will use this
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - Internship Adda</title>
    <link rel="icon" type="image/png" href="https://internshipadda.com/icons.png">
    <link rel="shortcut icon" type="image/png" href="https://internshipadda.com/icons.png">
    <link rel="apple-touch-icon" href="https://internshipadda.com/icons.png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0fdf4', 100: '#dcfce7', 200: '#bbf7d0', 300: '#86efac',
                            400: '#4ade80', 500: '#22c55e', 600: '#16a34a', 700: '#15803d',
                            800: '#166534', 900: '#14532d',
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        h1, h2 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
        }
        .otp-input {
            width: 50px;
            height: 60px;
            font-size: 24px;
            text-align: center;
            border: 2px solid #d1d5db;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .otp-input:focus {
            border-color: #22c55e;
            outline: none;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .5; }
        }
        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-primary-50 to-blue-50 min-h-screen flex items-center justify-center p-4">

<div class="w-full max-w-md">
    <!-- Logo -->
    <div class="text-center mb-8">
        <div class="inline-flex items-center gap-2 mb-4">
            <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-700 rounded-lg flex items-center justify-center">
                <span class="text-white font-bold text-2xl">I</span>
            </div>
            <span class="text-2xl font-bold text-gray-900">Internship Adda</span>
        </div>
        <h1 class="text-3xl font-bold text-gray-900 mt-4">Verify Your Email</h1>
        <p class="text-gray-600 mt-2">We sent a 6-digit code to</p>
        <p class="text-primary-600 font-semibold"><?php echo htmlspecialchars($email); ?></p>
    </div>

    <!-- Verification Card -->
    <div class="bg-white rounded-2xl shadow-xl p-8">
        <!-- Email Icon -->
        <div class="flex justify-center mb-6">
            <div class="w-16 h-16 bg-primary-100 rounded-full flex items-center justify-center">
                <svg class="w-8 h-8 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
            </div>
        </div>

        <form id="otpForm" class="space-y-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-3 text-center">Enter 6-Digit OTP</label>
                <div class="flex justify-center gap-2">
                    <input type="text" maxlength="1" class="otp-input" id="otp1" oninput="moveToNext(this, 'otp2')" onkeydown="handleBackspace(event, this, null)">
                    <input type="text" maxlength="1" class="otp-input" id="otp2" oninput="moveToNext(this, 'otp3')" onkeydown="handleBackspace(event, this, 'otp1')">
                    <input type="text" maxlength="1" class="otp-input" id="otp3" oninput="moveToNext(this, 'otp4')" onkeydown="handleBackspace(event, this, 'otp2')">
                    <input type="text" maxlength="1" class="otp-input" id="otp4" oninput="moveToNext(this, 'otp5')" onkeydown="handleBackspace(event, this, 'otp3')">
                    <input type="text" maxlength="1" class="otp-input" id="otp5" oninput="moveToNext(this, 'otp6')" onkeydown="handleBackspace(event, this, 'otp4')">
                    <input type="text" maxlength="1" class="otp-input" id="otp6" oninput="handleSubmit()" onkeydown="handleBackspace(event, this, 'otp5')">
                </div>
            </div>

            <div id="errorMessage" class="hidden bg-red-50 border-2 border-red-200 rounded-lg p-3 text-center">
                <p class="text-red-800 font-semibold text-sm"></p>
            </div>

            <button type="submit" id="verifyBtn" class="w-full bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                Verify Email
            </button>
        </form>

        <!-- Resend OTP -->
        <div class="mt-6 text-center">
            <p class="text-gray-600 text-sm mb-2">Didn't receive the code?</p>
            <button id="resendBtn" onclick="resendOTP()" class="text-primary-600 hover:text-primary-700 font-semibold text-sm">
                Resend OTP
            </button>
            <p id="timer" class="text-gray-500 text-xs mt-1"></p>
        </div>

        <!-- Change Email -->
        <div class="mt-4 text-center">
            <a href="/public/signup.php<?php echo !empty($redirectUrl) ? '?redirect=' . urlencode($redirectUrl) : ''; ?>" class="text-gray-500 hover:text-gray-700 text-sm">← Change Email</a>
        </div>
    </div>
</div>

<script>
    const EMAIL = <?php echo json_encode($email); ?>;
    const NAME = <?php echo json_encode($name); ?>;
    const REDIRECT_URL = <?php echo json_encode($redirectUrl); ?>;
    let resendCooldown = 60;
    let timerInterval;

    // Auto-focus first input
    document.getElementById('otp1').focus();

    function moveToNext(current, nextFieldId) {
        if (current.value.length >= 1) {
            if (nextFieldId) {
                document.getElementById(nextFieldId).focus();
            }
        }
    }

    function handleBackspace(event, current, prevFieldId) {
        if (event.key === 'Backspace' && current.value.length === 0 && prevFieldId) {
            document.getElementById(prevFieldId).focus();
        }
    }

    function handleSubmit() {
        const otp = getOTP();
        if (otp.length === 6) {
            verifyOTP(otp);
        }
    }

    function getOTP() {
        let otp = '';
        for (let i = 1; i <= 6; i++) {
            otp += document.getElementById('otp' + i).value;
        }
        return otp;
    }

    document.getElementById('otpForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const otp = getOTP();
        if (otp.length !== 6) {
            showError('Please enter all 6 digits');
            return;
        }
        await verifyOTP(otp);
    });

    async function verifyOTP(otp) {
        const verifyBtn = document.getElementById('verifyBtn');
        verifyBtn.disabled = true;
        verifyBtn.textContent = 'Verifying...';
        hideError();

        try {
            const response = await fetch('/api/verify-email.php?action=verify-otp', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: EMAIL, otp: otp })
            });

            const result = await response.json();

            if (result.success) {
                showSuccess('✅ Email verified! Completing registration...');

                setTimeout(() => {
                    // ✅ FIXED: redirect URL session mein already save hai (PHP mein)
                    // complete-registration.php pe sirf jaana hai
                    let completeUrl = '/public/complete-registration.php';
                    if (REDIRECT_URL) {
                        completeUrl += '?redirect=' + encodeURIComponent(REDIRECT_URL);
                    }
                    window.location.href = completeUrl;
                }, 1500);
            } else {
                showError(result.error || 'Invalid OTP. Please try again.');
                clearOTPInputs();
                document.getElementById('otp1').focus();
                verifyBtn.disabled = false;
                verifyBtn.textContent = 'Verify Email';
            }
        } catch (error) {
            console.error('Verification error:', error);
            showError('Network error. Please try again.');
            verifyBtn.disabled = false;
            verifyBtn.textContent = 'Verify Email';
        }
    }

    async function resendOTP() {
        const resendBtn = document.getElementById('resendBtn');
        if (resendCooldown > 0) return;

        resendBtn.disabled = true;
        resendBtn.textContent = 'Sending...';

        try {
            const response = await fetch('/api/verify-email.php?action=resend-otp', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: EMAIL, name: NAME })
            });

            const result = await response.json();

            if (result.success) {
                showSuccess('✅ New OTP sent to your email!');
                clearOTPInputs();
                document.getElementById('otp1').focus();
                startResendTimer();
            } else {
                showError(result.error || 'Failed to resend OTP');
                resendBtn.disabled = false;
                resendBtn.textContent = 'Resend OTP';
            }
        } catch (error) {
            showError('Network error. Please try again.');
            resendBtn.disabled = false;
            resendBtn.textContent = 'Resend OTP';
        }
    }

    function startResendTimer() {
        resendCooldown = 60;
        const resendBtn = document.getElementById('resendBtn');
        const timer = document.getElementById('timer');

        resendBtn.disabled = true;
        resendBtn.textContent = `Resend in ${resendCooldown}s`;

        timerInterval = setInterval(() => {
            resendCooldown--;
            if (resendCooldown > 0) {
                resendBtn.textContent = `Resend in ${resendCooldown}s`;
                timer.textContent = '';
            } else {
                clearInterval(timerInterval);
                resendBtn.disabled = false;
                resendBtn.textContent = 'Resend OTP';
                timer.textContent = '';
            }
        }, 1000);
    }

    startResendTimer();

    function clearOTPInputs() {
        for (let i = 1; i <= 6; i++) {
            document.getElementById('otp' + i).value = '';
        }
    }

    function showError(message) {
        const errorDiv = document.getElementById('errorMessage');
        errorDiv.querySelector('p').textContent = message;
        errorDiv.classList.remove('hidden');
        errorDiv.classList.add('bg-red-50', 'border-red-200');
        errorDiv.classList.remove('bg-green-50', 'border-green-200');
        errorDiv.querySelector('p').classList.remove('text-green-800');
        errorDiv.querySelector('p').classList.add('text-red-800');
    }

    function showSuccess(message) {
        const errorDiv = document.getElementById('errorMessage');
        errorDiv.querySelector('p').textContent = message;
        errorDiv.classList.remove('hidden');
        errorDiv.classList.add('bg-green-50', 'border-green-200');
        errorDiv.classList.remove('bg-red-50', 'border-red-200');
        errorDiv.querySelector('p').classList.remove('text-red-800');
        errorDiv.querySelector('p').classList.add('text-green-800');
    }

    function hideError() {
        document.getElementById('errorMessage').classList.add('hidden');
    }
</script>

</body>
</html>