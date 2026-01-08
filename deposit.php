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
    <title>CSC Platform - Deposit</title>
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            max-width: 480px;
            width: 100%;
        }

        .card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 40px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        }

        h1 {
            color: #fff;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .subtitle {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
            margin-bottom: 32px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        label {
            display: block;
            color: #fff;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        input {
            width: 100%;
            padding: 14px 16px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            color: #fff;
            font-size: 15px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
        }

        #card-element {
            padding: 14px 16px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
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
            color: #ff6b9d;
            font-size: 13px;
            margin-top: 8px;
            min-height: 20px;
        }

        .amount-section {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .amount-label {
            color: rgba(255, 255, 255, 0.7);
            font-size: 13px;
            margin-bottom: 4px;
        }

        .amount-value {
            color: #fff;
            font-size: 32px;
            font-weight: 600;
        }

        button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            color: #fff;
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
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .success-message {
            background: rgba(76, 217, 100, 0.2);
            border: 1px solid rgba(76, 217, 100, 0.4);
            color: #4cd964;
            padding: 16px;
            border-radius: 12px;
            text-align: center;
            margin-top: 20px;
            backdrop-filter: blur(10px);
        }

        .error-message {
            background: rgba(255, 107, 157, 0.2);
            border: 1px solid rgba(255, 107, 157, 0.4);
            color: #ff6b9d;
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
            color: rgba(255, 255, 255, 0.6);
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
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            color: white;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.2);
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
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            text-align: center;
            color: white;
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
            color: #fff;
            font-size: 14px;
            word-break: break-all;
            margin-top: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
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

    <div class="container">
        <div class="card">
            <h1>Deposit Funds</h1>
            <p class="subtitle">Add USD to your CSC Platform account</p>

            <?php if (isset($success)): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <div class="payment-methods">
                <div class="payment-method active" onclick="switchMethod('stripe')">Credit Card</div>
                <div class="payment-method" onclick="switchMethod('bitcoin')">Bitcoin</div>
            </div>

            <!-- Stripe Form -->
            <form id="stripe-form" method="POST" style="display: block;">
                <div class="amount-section">
                    <div class="amount-label">Amount to deposit</div>
                    <div class="form-group">
                        <input type="number" name="amount" step="0.01" min="1" placeholder="Enter amount" required value="10.00">
                    </div>
                </div>

                <div class="form-group">
                    <label>Cardholder Name</label>
                    <input type="text" id="name" placeholder="John Doe" required>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" id="email" placeholder="john@example.com" required>
                </div>

                <div class="form-group">
                    <label>Card Details</label>
                    <div id="card-element"></div>
                    <div id="card-errors" role="alert"></div>
                </div>

                <button type="submit" id="submit-button">
                    <span id="button-text">Deposit Funds</span>
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
                    <p style="color: rgba(255, 255, 255, 0.7); font-size: 14px; margin-top: 10px;">
                        Send Bitcoin to the address below. Your account will be credited after 3 confirmations.
                    </p>
                </div>

                <div class="btc-address">
                    3J98t1WpEZ73CNmQviecrnyiWrnqRhWNLy
                </div>

                <div style="margin-top: 20px; text-align: center;">
                    <div style="display: inline-block; padding: 10px; background: rgba(255, 255, 255, 0.05); border-radius: 8px;">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=bitcoin:3J98t1WpEZ73CNmQviecrnyiWrnqRhWNLy" 
                             alt="Bitcoin QR Code" style="width: 150px; height: 150px; border-radius: 8px;">
                    </div>
                </div>

                <div style="margin-top: 20px; padding: 15px; background: rgba(255, 255, 255, 0.05); border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.1);">
                    <p style="color: rgba(255, 255, 255, 0.7); font-size: 12px; margin-bottom: 5px;">⚠️ Important Notes:</p>
                    <ul style="color: rgba(255, 255, 255, 0.7); font-size: 12px; padding-left: 20px;">
                        <li>Minimum deposit: 0.0001 BTC</li>
                        <li>Network fee: 0.0005 BTC</li>
                        <li>Processing time: ~30 minutes</li>
                        <li>Exchange rate: Market price at confirmation</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Switch between payment methods
        function switchMethod(method) {
            const stripeForm = document.getElementById('stripe-form');
            const bitcoinForm = document.getElementById('bitcoin-form');
            const methods = document.querySelectorAll('.payment-method');
            
            methods.forEach(m => m.classList.remove('active'));
            event.target.classList.add('active');
            
            if (method === 'stripe') {
                stripeForm.style.display = 'block';
                bitcoinForm.style.display = 'none';
            } else {
                stripeForm.style.display = 'none';
                bitcoinForm.style.display = 'block';
            }
        }

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

        form.addEventListener('submit', async function(event) {
            event.preventDefault();

            submitButton.disabled = true;
            buttonText.style.display = 'none';
            spinner.style.display = 'inline-block';

            const name = document.getElementById('name').value;
            const email = document.getElementById('email').value;
            const amount = document.querySelector('input[name="amount"]').value;

            try {
                // For demo purposes, we'll simulate a successful payment
                // In production, you would use Stripe.js and your backend
                
                // Simulate API call delay
                await new Promise(resolve => setTimeout(resolve, 1500));
                
                // Submit the form for server-side processing
                form.submit();

            } catch (error) {
                document.getElementById('error-message').textContent = error.message;
                document.getElementById('error-message').style.display = 'block';
                submitButton.disabled = false;
                buttonText.style.display = 'inline';
                spinner.style.display = 'none';

                setTimeout(() => {
                    document.getElementById('error-message').style.display = 'none';
                }, 5000);
            }
        });
    </script>
</body>
</html>