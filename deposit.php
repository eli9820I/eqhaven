<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

$config = json_decode(file_get_contents('config.json'), true);

// Stripe
require_once 'vendor/autoload.php'; // You'll need to install Stripe PHP SDK
\Stripe\Stripe::setApiKey($config['stripe_secret_key']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stripeToken'])) {
    $amount = floatval($_POST['amount']) * 100; // Convert to cents
    
    try {
        $charge = \Stripe\Charge::create([
            'amount' => $amount,
            'currency' => 'usd',
            'description' => 'CSC Deposit',
            'source' => $_POST['stripeToken'],
        ]);
        
        if ($charge->paid) {
            // Update user balance
            $users = json_decode(file_get_contents('users.json'), true);
            foreach ($users as &$user) {
                if ($user['id'] === $_SESSION['user_id']) {
                    $user['usd_balance'] += ($amount / 100);
                    break;
                }
            }
            file_put_contents('users.json', json_encode($users));
            
            header('Location: wallet.php?deposit=success');
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Deposit</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://js.stripe.com/v3/"></script>
</head>
<body>
    <nav>
        <div class="nav-brand">CSC Platform</div>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="trade.php">Trade</a>
            <a href="wallet.php">Wallet</a>
            <a href="deposit.php" class="active">Deposit</a>
            <a href="blockchain.php">Blockchain</a>
            <span><?php echo $_SESSION['user_name']; ?></span>
            <a href="logout.php">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <h1>Deposit Funds</h1>
        
        <div class="deposit-methods">
            <div class="deposit-method">
                <h3>Credit/Debit Card (Stripe)</h3>
                <form id="stripe-form" method="POST">
                    <input type="number" name="amount" step="0.01" min="1" placeholder="Amount (USD)" required>
                    <div id="card-element">
                        <!-- Stripe Card Element will be inserted here -->
                    </div>
                    <div id="card-errors"></div>
                    <button type="submit">Deposit</button>
                    <input type="hidden" name="stripeToken" id="stripeToken">
                </form>
            </div>
            
            <div class="deposit-method">
                <h3>Bitcoin Deposit</h3>
                <p>Send Bitcoin to this address:</p>
                <div class="btc-address">1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa</div>
                <small>Send any amount. CSC will be credited after 3 confirmations.</small>
            </div>
        </div>
    </div>
    
    <script>
        const stripe = Stripe('<?php echo $config['stripe_public_key']; ?>');
        const elements = stripe.elements();
        const card = elements.create('card');
        card.mount('#card-element');
        
        const form = document.getElementById('stripe-form');
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const {token, error} = await stripe.createToken(card);
            
            if (error) {
                document.getElementById('card-errors').textContent = error.message;
            } else {
                document.getElementById('stripeToken').value = token.id;
                form.submit();
            }
        });
    </script>
</body>
</html>