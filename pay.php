<?php
$message = '';
if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        html, body { height: 100%; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            display: block;
            padding: 0;
            color: #fff;
            margin: 0;
            overflow-x: hidden;
            background-color: #000;
            font-family: 'Inter', system-ui, sans-serif;
        }
        @keyframes drift-zoom {
            0% { background-size: 100% 100%; background-position: 0% 50%; }
            100% { background-size: 135% 135%; background-position: 100% 50%; }
        }
        .fixed-background {
            position: fixed;
            top: 0; left: 0;
            width: 100vw; height: 100vh;
            z-index: -1;
            animation: drift-zoom 8s linear infinite alternate;
            background-image:
                linear-gradient(rgba(0,0,0,0.2), rgba(0,0,0,0.2)),
                url('images/darma.jpg');
            background-size: 110% 110%;
            background-position: 0% 50%;
            background-repeat: no-repeat;
            will-change: background-size, background-position;
        }
        .payment-logo {
            height: 40px;
            width: auto;
            object-fit: contain;
        }
        .payment-button {
            background: rgba(255,255,255,0.25);
            backdrop-filter: blur(8px);
            border-radius: 0.75rem;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 0.75rem;
            height: 6rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.25s ease;
        }
        .payment-button:hover {
            background: rgba(255,255,255,0.4);
            border-color: rgba(255,255,255,0.6);
            box-shadow: 0 8px 20px rgba(0,0,0,0.4);
            transform: translateY(-2px);
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }
        .alert-box {
            background: rgba(34,197,94,0.2);
            border: 1px solid rgba(34,197,94,0.4);
            color: #bbf7d0;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 10px;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="fixed-background"></div>

<div class="min-h-screen text-white p-4 md:p-8 flex items-center justify-center">
    <div class="bg-black/60 backdrop-blur-lg rounded-2xl shadow-xl border border-white/30 w-full max-w-4xl p-6 md:p-8">

        <header class="mb-6 pb-6 border-b border-white/30">
            <?php if ($message): ?>
                <div class="alert-box"><?php echo $message; ?></div>
            <?php endif; ?>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="bg-blue-600 text-white font-bold p-2 rounded-lg text-lg">
                        123
                    </div>
                    <span class="text-sm md:text-base font-medium">
                        Thank you for shopping with <strong>123.com.bd</strong>
                    </span>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8">
            <div class="md:col-span-2">
                <h2 class="text-xl font-semibold mb-4">Select a Payment Method</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                    <button class="payment-button">
                        <img src="images/bkash.png" alt="bKash" class="payment-logo">
                        <span class="font-bold text-lg">bKash</span>
                    </button>
                    <button class="payment-button">
                        <img src="images/nagad.png" alt="Nagad" class="payment-logo">
                        <span class="font-bold text-lg">Nagad</span>
                    </button>
                    <button class="payment-button">
                        <img src="images/rocket.png" alt="Rocket" class="payment-logo">
                        <span class="font-bold text-lg">Rocket</span>
                    </button>
                    <button class="payment-button">
                        <img src="images/visa.jpg" alt="VISA" class="payment-logo">
                        <span class="font-bold text-lg">VISA</span>
                    </button>
                    <button class="payment-button">
                        <img src="images/master.png" alt="Mastercard" class="payment-logo">
                        <span class="font-bold text-lg">Mastercard</span>
                    </button>
                    <button class="payment-button">
                        <img src="images/amex.png" alt="AMEX" class="payment-logo">
                        <span class="font-bold text-lg">AMEX</span>
                    </button>
                    <button class="payment-button">
                        <img src="images/dbbl.jpg" alt="DBBL Nexus" class="payment-logo">
                        <span class="font-bold text-lg">DBBL Nexus</span>
                    </button>
                    <button class="payment-button">
                        <img src="images/paypal.png" alt="PayPal" class="payment-logo">
                        <span class="font-bold text-lg">PayPal</span>
                    </button>
                </div>
            </div>

            <div class="md:col-span-1">
                <div class="bg-black/70 backdrop-blur-md rounded-xl p-5 border border-white/20 h-full flex flex-col">
                    <h2 class="text-xl font-semibold mb-5 pb-4 border-b border-white/30 text-center">
                        Payment Summary
                    </h2>

                    <div class="space-y-3 text-sm flex-grow">
                        <div class="summary-row">
                            <span class="text-white/80">Merchant:</span>
                            <span class="font-medium text-right">123</span>
                        </div>
                        <div class="summary-row">
                            <span class="text-white/80">Invoice To:</span>
                            <span class="font-medium text-right">Mr. User Name</span>
                        </div>
                        <div class="summary-row">
                            <span class="text-white/80">Mobile:</span>
                            <span class="font-medium text-right">01719023450</span>
                        </div>
                        <div class="summary-row">
                            <span class="text-white/80">Email:</span>
                            <span class="font-medium text-right truncate">username@gmail.com</span>
                        </div>
                        <div class="summary-row">
                            <span class="text-white/80">Order No:</span>
                            <span class="font-medium text-right">294</span>
                        </div>
                        <div class="summary-row">
                            <span class="text-white/80">3Pay-ID:</span>
                            <span class="font-medium text-right truncate">C9Yc89...</span>
                        </div>
                        <div class="summary-row">
                            <span class="text-white/80">Invoice Amount:</span>
                            <span class="font-medium text-right">tk 1.00</span>
                        </div>
                        <div class="summary-row">
                            <span class="text-white/80">Order Details:</span>
                            <span class="font-medium text-right">Canon Digital SLR</span>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-white/30">
                        <div class="flex justify-between items-center">
                            <span class="text-lg font-bold">TOTAL:</span>
                            <span class="text-2xl font-bold">tk 1.00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <footer class="flex items-center justify-between mt-8 pt-6 border-t border-white/30">
            <a href="hall.php" class="bg-white/20 backdrop-blur-sm border border-white/30 rounded-lg px-5 py-2 text-sm font-medium transition-all duration-300 hover:bg-white/40">
                &lt; Cancel
            </a>
            <div class="text-sm flex items-center gap-2">
                <span class="text-white/80">Powered By:</span>
                <span class="bg-red-600 text-white font-bold p-2 rounded-lg text-lg">3pay</span>
            </div>
        </footer>
    </div>
</div>
</body>
</html>
