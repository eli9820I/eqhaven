<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: auth.php');
    exit;
}

$config = json_decode(file_get_contents('config.json'), true);

// Simple deposit handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount']);
    
    if ($amount >= 1) {
        // Calculate how much CSC they get at current price
        $cscReceived = $amount / $config['price'];
        
        $users = json_decode(file_get_contents('users.json'), true);
        foreach ($users as &$user) {
            if ($user['id'] === $_SESSION['user_id']) {
                // Add CSC to their balance
                $user['balance'] += $cscReceived;
                break;
            }
        }
        file_put_contents('users.json', json_encode($users));
        
        // UPDATE CONFIG.JSON
        // 1. Increase price by 5%
        $config['price'] += ($config['price'] * 0.05);
        
        // 2. Track circulating supply
        if (!isset($config['circulating'])) {
            $config['circulating'] = 0;
        }
        $config['circulating'] += $cscReceived;
        
        // 3. Track total deposits
        if (!isset($config['total_deposits'])) {
            $config['total_deposits'] = 0;
        }
        $config['total_deposits'] += $amount;
        
        file_put_contents('config.json', json_encode($config));
        
        // Add transaction
        $transactions = file_exists('transactions.json') ? json_decode(file_get_contents('transactions.json'), true) : [];
        $transaction = [
            'id' => uniqid(),
            'type' => 'deposit',
            'user_id' => $_SESSION['user_id'],
            'amount' => $cscReceived,
            'price' => $config['price'], // New price after 5% increase
            'total' => $amount,
            'timestamp' => date('Y-m-d H:i:s'),
            'note' => 'Deposit +5% price increase'
        ];
        $transactions[] = $transaction;
        file_put_contents('transactions.json', json_encode($transactions));
        
        // Add to blockchain
        $blockchain = file_exists('blockchain.json') ? json_decode(file_get_contents('blockchain.json'), true) : [];
        $lastBlock = end($blockchain);
        $previousHash = $lastBlock ? $lastBlock['hash'] : '0';
        
        $newBlock = [
            'index' => count($blockchain),
            'timestamp' => time(),
            'transactions' => [$transaction],
            'previous_hash' => $previousHash,
            'hash' => hash('sha256', json_encode($transaction) . $previousHash),
            'nonce' => rand(1000, 9999)
        ];
        
        $blockchain[] = $newBlock;
        file_put_contents('blockchain.json', json_encode($blockchain));
        
        $success = "✅ Deposit successful! You received <strong>" . number_format($cscReceived, 2) . " CSC</strong>.<br>New price: <strong>$" . number_format($config['price'], 4) . "</strong> (+5%)";
    } else {
        $error = "Minimum deposit amount is $1.00";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - CSC Platform</title>
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --gradient-start: #667eea;
            --gradient-end: #764ba2;
            --accent-primary: #3b82f6;
            --accent-secondary: #8b5cf6;
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.7);
            --text-tertiary: rgba(255, 255, 255, 0.5);
            --bg-card: rgba(255, 255, 255, 0.1);
            --bg-input: rgba(255, 255, 255, 0.1);
            --border-color: rgba(255, 255, 255, 0.2);
            --success-color: #4cd964;
            --error-color: #ff6b9d;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .checkout-container {
            max-width: 480px;
            width: 100%;
        }

        .card {
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 40px;
            border: 1px solid var(--border-color);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        }

        h1 {
            color: var(--text-primary);
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .subtitle {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 32px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        label {
            display: block;
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        input {
            width: 100%;
            padding: 14px 16px;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 15px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        input::placeholder {
            color: var(--text-tertiary);
        }

        input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
        }

        #card-element {
            padding: 14px 16px;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        #card-element.StripeElement--focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
        }

        #card-errors {
            color: var(--error-color);
            font-size: 13px;
            margin-top: 8px;
            min-height: 20px;
        }

        .amount-section {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
        }

        .amount-label {
            color: var(--text-secondary);
            font-size: 13px;
            margin-bottom: 4px;
        }

        .amount-value {
            color: var(--text-primary);
            font-size: 32px;
            font-weight: 600;
        }

        button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
            border: none;
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px 0 rgba(102, 126, 234, 0.4);
        }

        button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px 0 rgba(102, 126, 234, 0.6);
        }

        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: var(--text-primary);
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .success-message {
            background: rgba(76, 217, 100, 0.2);
            border: 1px solid rgba(76, 217, 100, 0.4);
            color: var(--success-color);
            padding: 16px;
            border-radius: 12px;
            text-align: center;
            margin-top: 20px;
            backdrop-filter: blur(10px);
        }

        .error-message {
            background: rgba(255, 107, 157, 0.2);
            border: 1px solid rgba(255, 107, 157, 0.4);
            color: var(--error-color);
            padding: 16px;
            border-radius: 12px;
            text-align: center;
            margin-top: 20px;
            backdrop-filter: blur(10px);
        }

        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: var(--text-tertiary);
            font-size: 12px;
            margin-top: 20px;
        }

        .lock-icon {
            width: 12px;
            height: 12px;
        }

        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            padding: 12px 24px;
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            z-index: 100;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .payment-methods {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .payment-method {
            flex: 1;
            padding: 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            text-align: center;
            color: var(--text-primary);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .payment-method.active {
            background: rgba(102, 126, 234, 0.3);
            border-color: rgba(102, 126, 234, 0.6);
        }

        .payment-method:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .btc-address {
            font-family: monospace;
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            color: var(--text-primary);
            font-size: 14px;
            word-break: break-all;
            margin-top: 20px;
            border: 1px solid var(--border-color);
            letter-spacing: 0.5px;
        }

        .qr-container {
            margin-top: 20px;
            text-align: center;
        }

        .qr-code {
            display: inline-block;
            padding: 10px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .qr-code img {
            width: 150px;
            height: 150px;
            border-radius: 8px;
            display: block;
        }

        .info-box {
            margin-top: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .info-title {
            color: var(--text-primary);
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .info-list {
            color: var(--text-secondary);
            font-size: 12px;
            padding-left: 20px;
        }

        .info-list li {
            margin-bottom: 4px;
        }

        .info-list li:last-child {
            margin-bottom: 0;
        }

        .amount-input-group {
            position: relative;
        }

        .amount-input-group::before {
            content: '$';
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-primary);
            font-weight: 500;
        }

        .amount-input-group input {
            padding-left: 32px;
        }

        .quick-amounts {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 15px;
        }

        .quick-amount {
            padding: 10px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 500;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .quick-amount:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .quick-amount.active {
            background: rgba(102, 126, 234, 0.3);
            border-color: rgba(102, 126, 234, 0.6);
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .loading-content {
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            padding: 30px;
            border-radius: 16px;
            text-align: center;
            border: 1px solid var(--border-color);
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(255, 255, 255, 0.1);
            border-top-color: var(--text-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        .loading-text {
            color: var(--text-primary);
            font-size: 14px;
        }

        .conversion-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }

        .conversion-rate {
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .csc-received {
            color: var(--success-color);
            font-size: 18px;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <a href="index.php" class="back-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M19 12H5M12 19l-7-7 7-7"></path>
        </svg>
        Back to Dashboard
    </a>

    <div class="checkout-container">
        <div class="card">
            <h1>Deposit & Get CSC</h1>
            <p class="subtitle">Deposit USD to receive CSC tokens instantly</p>

            <?php if (isset($success)): ?>
            <div class="success-message">
                <?php echo $success; ?>
                <div style="margin-top: 10px; font-size: 12px; color: rgba(76, 217, 100, 0.8);">
                    Price increased by 5% due to deposit activity
                </div>
            </div>
            <script>
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 5000);
            </script>
            <?php endif; ?>

            <?php if (isset($error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <!-- Current Price Display -->
            <div class="conversion-info">
                <div class="conversion-rate">
                    Current Price: <strong>$<?php echo number_format($config['price'], 4); ?></strong> per CSC
                </div>
                <div class="csc-received" id="csc-preview">
                    $10.00 = <?php echo number_format(10 / $config['price'], 2); ?> CSC
                </div>
            </div>

            <div class="payment-methods">
                <div class="payment-method active" id="stripe-tab" onclick="switchMethod('stripe')">
                    Credit Card
                </div>
                <div class="payment-method" id="bitcoin-tab" onclick="switchMethod('bitcoin')">
                    Bitcoin
                </div>
            </div>

            <!-- Stripe Form -->
            <form id="stripe-form" method="POST" style="display: block;">
                <div class="amount-section">
                    <div class="amount-label">Deposit Amount (USD)</div>
                    <div class="form-group amount-input-group">
                        <input type="number" name="amount" step="0.01" min="1" placeholder="0.00" required value="10.00" id="deposit-amount">
                    </div>
                    
                    <div class="quick-amounts">
                        <div class="quick-amount" onclick="setAmount(10)">$10</div>
                        <div class="quick-amount" onclick="setAmount(25)">$25</div>
                        <div class="quick-amount" onclick="setAmount(50)">$50</div>
                        <div class="quick-amount" onclick="setAmount(100)">$100</div>
                        <div class="quick-amount" onclick="setAmount(250)">$250</div>
                        <div class="quick-amount" onclick="setAmount(500)">$500</div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="name">Cardholder Name</label>
                    <input type="text" id="name" placeholder="John Doe" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" placeholder="john@example.com" required>
                </div>

                <div class="form-group">
                    <label for="card-element">Card Details</label>
                    <div id="card-element"></div>
                    <div id="card-errors" role="alert"></div>
                </div>

                <button type="submit" id="submit-button">
                    <span id="button-text">Deposit & Get CSC</span>
                    <span id="spinner" class="spinner" style="display: none;"></span>
                </button>

                <div class="security-badge">
                    <svg class="lock-icon" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                    </svg>
                    Secured by Stripe
                </div>
            </form>

            <!-- Bitcoin Form -->
            <div id="bitcoin-form" style="display: none;">
                <div class="amount-section">
                    <div class="amount-label">Bitcoin Deposit (Gets CSC)</div>
                    <p style="color: var(--text-secondary); font-size: 14px; margin-top: 10px; line-height: 1.5;">
                        Send Bitcoin to get CSC tokens. Amount of CSC received depends on market rate at confirmation.
                    </p>
                </div>

                <div class="btc-address" id="bitcoin-address">
                    3J98t1WpEZ73CNmQviecrnyiWrnqRhWNLy
                </div>

                <div class="qr-container">
                    <div class="qr-code">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=bitcoin:3J98t1WpEZ73CNmQviecrnyiWrnqRhWNLy&bgcolor=667eea&color=ffffff" 
                             alt="Bitcoin QR Code">
                    </div>
                </div>

                <div class="info-box">
                    <div class="info-title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        How Bitcoin Deposits Work
                    </div>
                    <ul class="info-list">
                        <li>Send Bitcoin to the address above</li>
                        <li>After 3 confirmations, we convert BTC to USD at market rate</li>
                        <li>You receive CSC tokens at current price (+5% price increase)</li>
                        <li>Network fee: 0.0005 BTC (paid by you)</li>
                        <li>Processing time: ~30 minutes (3 confirmations)</li>
                    </ul>
                </div>
            </div>

            <div id="success-message" style="display: none;" class="success-message"></div>
            <div id="error-message" style="display: none;" class="error-message"></div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <div class="loading-text">Processing deposit & minting CSC tokens...</div>
        </div>
    </div>

    <script>
        // Get current price from PHP
        const currentPrice = <?php echo $config['price']; ?>;

        // Switch between payment methods
        function switchMethod(method) {
            const stripeForm = document.getElementById('stripe-form');
            const bitcoinForm = document.getElementById('bitcoin-form');
            const stripeTab = document.getElementById('stripe-tab');
            const bitcoinTab = document.getElementById('bitcoin-tab');
            
            // Update tabs
            stripeTab.classList.remove('active');
            bitcoinTab.classList.remove('active');
            
            if (method === 'stripe') {
                stripeTab.classList.add('active');
                stripeForm.style.display = 'block';
                bitcoinForm.style.display = 'none';
            } else {
                bitcoinTab.classList.add('active');
                stripeForm.style.display = 'none';
                bitcoinForm.style.display = 'block';
            }
        }

        // Calculate CSC received for amount
        function calculateCSC(usdAmount) {
            return usdAmount / currentPrice;
        }

        // Update CSC preview
        function updateCSCPreview(amount) {
            const cscAmount = calculateCSC(amount);
            const cscPreview = document.getElementById('csc-preview');
            cscPreview.innerHTML = `$${amount.toFixed(2)} = <strong>${cscAmount.toFixed(2)} CSC</strong>`;
        }

        // Set quick amount
        function setAmount(amount) {
            const input = document.getElementById('deposit-amount');
            
            input.value = amount;
            updateCSCPreview(amount);
            
            // Update quick amount buttons
            document.querySelectorAll('.quick-amount').forEach(btn => {
                btn.classList.remove('active');
                if (parseInt(btn.textContent.replace('$', '')) === amount) {
                    btn.classList.add('active');
                }
            });
        }

        // Update preview when input changes
        document.getElementById('deposit-amount').addEventListener('input', function() {
            const amount = parseFloat(this.value) || 0;
            updateCSCPreview(amount);
            
            // Update quick amount buttons
            document.querySelectorAll('.quick-amount').forEach(btn => {
                btn.classList.remove('active');
            });
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            const initialAmount = parseFloat(document.getElementById('deposit-amount').value);
            updateCSCPreview(initialAmount);
            
            document.querySelectorAll('.quick-amount').forEach(btn => {
                if (parseInt(btn.textContent.replace('$', '')) === initialAmount) {
                    btn.classList.add('active');
                }
            });
        });

        // Stripe integration
        const stripe = Stripe('<?php echo $config['stripe_public_key']; ?>');
        const elements = stripe.elements();

        const style = {
            base: {
                color: '#fff',
                fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif',
                fontSmoothing: 'antialiased',
                fontSize: '15px',
                '::placeholder': {
                    color: 'rgba(255, 255, 255, 0.5)'
                }
            },
            invalid: {
                color: '#ff6b9d',
                iconColor: '#ff6b9d'
            }
        };

        const cardElement = elements.create('card', { style: style });
        cardElement.mount('#card-element');

        cardElement.on('change', function(event) {
            const displayError = document.getElementById('card-errors');
            if (event.error) {
                displayError.textContent = event.error.message;
            } else {
                displayError.textContent = '';
            }
        });

        const form = document.getElementById('stripe-form');
        const submitButton = document.getElementById('submit-button');
        const buttonText = document.getElementById('button-text');
        const spinner = document.getElementById('spinner');
        const loadingOverlay = document.getElementById('loading-overlay');

        form.addEventListener('submit', async function(event) {
            event.preventDefault();

            // Validate amount
            const amount = parseFloat(document.getElementById('deposit-amount').value);
            if (amount < 1) {
                showError('Minimum deposit amount is $1.00');
                return;
            }

            // Validate email
            const email = document.getElementById('email').value;
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email)) {
                showError('Please enter a valid email address');
                return;
            }

            // Validate name
            const name = document.getElementById('name').value;
            if (!name.trim()) {
                showError('Please enter cardholder name');
                return;
            }

            submitButton.disabled = true;
            buttonText.style.display = 'none';
            spinner.style.display = 'inline-block';

            try {
                // Show loading overlay
                loadingOverlay.style.display = 'flex';

                // For demo purposes, we'll simulate a successful payment
                await new Promise(resolve => setTimeout(resolve, 2000));
                
                // Submit the form for server-side processing
                form.submit();

            } catch (error) {
                showError(error.message || 'Payment failed. Please try again.');
                submitButton.disabled = false;
                buttonText.style.display = 'inline';
                spinner.style.display = 'none';
                loadingOverlay.style.display = 'none';
            }
        });

        function showError(message) {
            const errorElement = document.getElementById('error-message');
            errorElement.textContent = message;
            errorElement.style.display = 'block';
            
            setTimeout(() => {
                errorElement.style.display = 'none';
            }, 5000);
        }

        // Copy Bitcoin address to clipboard
        document.getElementById('bitcoin-address').addEventListener('click', function() {
            const text = this.textContent;
            navigator.clipboard.writeText(text).then(() => {
                const originalText = this.textContent;
                this.textContent = 'Copied to clipboard!';
                this.style.color = 'var(--success-color)';
                
                setTimeout(() => {
                    this.textContent = originalText;
                    this.style.color = 'var(--text-primary)';
                }, 2000);
            });
        });
    </script>
</body>
</html>