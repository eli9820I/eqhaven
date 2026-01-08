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
        $users = json_decode(file_get_contents('users.json'), true);
        foreach ($users as &$user) {
            if ($user['id'] === $_SESSION['user_id']) {
                $user['usd_balance'] += $amount;
                break;
            }
        }
        file_put_contents('users.json', json_encode($users));
        
        // Add transaction
        $transactions = file_exists('transactions.json') ? json_decode(file_get_contents('transactions.json'), true) : [];
        $transaction = [
            'id' => uniqid(),
            'type' => 'deposit',
            'user_id' => $_SESSION['user_id'],
            'amount' => $amount,
            'method' => 'stripe',
            'status' => 'completed',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        $transactions[] = $transaction;
        file_put_contents('transactions.json', json_encode($transactions));
        
        $success = "Deposit of $" . number_format($amount, 2) . " successful!";
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

        /* Custom amount input styles */
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

        /* Quick amount buttons */
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

        /* Loading overlay */
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

        /* Confirmation message */
        .confirmation {
            text-align: center;
            padding: 20px;
        }

        .confirmation-icon {
            width: 60px;
            height: 60px;
            background: rgba(76, 217, 100, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }

        .confirmation-icon svg {
            width: 30px;
            height: 30px;
            color: var(--success-color);
        }

        .confirmation h3 {
            color: var(--text-primary);
            font-size: 20px;
            margin-bottom: 8px;
        }

        .confirmation p {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 20px;
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
            <h1>Secure Checkout</h1>
            <p class="subtitle">Complete your deposit securely</p>

            <?php if (isset($success)): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($success); ?>
                <div style="margin-top: 10px; font-size: 12px;">You will be redirected to your dashboard shortly...</div>
            </div>
            <script>
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 3000);
            </script>
            <?php endif; ?>

            <?php if (isset($error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

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
                    <div class="amount-label">Amount to deposit</div>
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
                    <input type="email" id="email" placeholder="john@example.com" required value="<?php echo isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="card-element">Card Details</label>
                    <div id="card-element"></div>
                    <div id="card-errors" role="alert"></div>
                </div>

                <button type="submit" id="submit-button">
                    <span id="button-text">Deposit $<span id="display-amount">10.00</span></span>
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
                    <div class="amount-label">Bitcoin Deposit</div>
                    <p style="color: var(--text-secondary); font-size: 14px; margin-top: 10px; line-height: 1.5;">
                        Send Bitcoin to the address below. Your account will be credited after 3 confirmations.
                        The deposit amount will be calculated based on the market rate at confirmation.
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
                        Important Notes
                    </div>
                    <ul class="info-list">
                        <li>Minimum deposit: 0.0001 BTC</li>
                        <li>Network fee: 0.0005 BTC (paid by you)</li>
                        <li>Processing time: ~30 minutes (3 confirmations)</li>
                        <li>Exchange rate: Market price at confirmation time</li>
                        <li>Send only Bitcoin (BTC) to this address</li>
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
            <div class="loading-text">Processing your deposit...</div>
        </div>
    </div>

    <script>
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

        // Set quick amount
        function setAmount(amount) {
            const input = document.getElementById('deposit-amount');
            const display = document.getElementById('display-amount');
            
            input.value = amount;
            display.textContent = amount.toFixed(2);
            
            // Update quick amount buttons
            document.querySelectorAll('.quick-amount').forEach(btn => {
                btn.classList.remove('active');
                if (parseInt(btn.textContent.replace('$', '')) === amount) {
                    btn.classList.add('active');
                }
            });
        }

        // Update display amount when input changes
        document.getElementById('deposit-amount').addEventListener('input', function() {
            const display = document.getElementById('display-amount');
            display.textContent = parseFloat(this.value).toFixed(2);
            
            // Update quick amount buttons
            document.querySelectorAll('.quick-amount').forEach(btn => {
                btn.classList.remove('active');
            });
        });

        // Initialize quick amount button states
        document.addEventListener('DOMContentLoaded', function() {
            const initialAmount = parseFloat(document.getElementById('deposit-amount').value);
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
                // In production, you would use Stripe.js and your backend
                
                // Simulate API call delay
                await new Promise(resolve => setTimeout(resolve, 2000));
                
                // Show success message
                const successMessage = document.getElementById('success-message');
                successMessage.textContent = `Payment of $${amount.toFixed(2)} successful! Processing deposit...`;
                successMessage.style.display = 'block';
                
                // Hide form
                form.style.display = 'none';
                
                // Hide loading overlay
                loadingOverlay.style.display = 'none';
                
                // Submit the form for server-side processing
                setTimeout(() => {
                    form.submit();
                }, 1000);

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