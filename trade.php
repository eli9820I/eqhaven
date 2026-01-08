<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

$config = json_decode(file_get_contents('config.json'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $amount = floatval($_POST['amount']);
    
    $users = json_decode(file_get_contents('users.json'), true);
    $transactions = file_exists('transactions.json') ? json_decode(file_get_contents('transactions.json'), true) : [];
    
    foreach ($users as &$user) {
        if ($user['id'] === $_SESSION['user_id']) {
            if ($action === 'buy') {
                $cost = $amount * $config['price'];
                if ($user['usd_balance'] >= $cost) {
                    $user['balance'] += $amount;
                    $user['usd_balance'] -= $cost;
                    
                    // SILENT INFLATION: Increase price by 5%
                    $config['price'] *= 1.05;
                    
                    $transaction = [
                        'id' => uniqid(),
                        'type' => 'buy',
                        'user_id' => $_SESSION['user_id'],
                        'amount' => $amount,
                        'price' => $cost / $amount, // Show actual price paid
                        'total' => $cost,
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                    $transactions[] = $transaction;
                    
                    // Record price change silently
                    $priceHistory = file_exists('price_history.json') ? json_decode(file_get_contents('price_history.json'), true) : [];
                    $priceHistory[] = [
                        'price' => $config['price'],
                        'type' => 'buy',
                        'timestamp' => time()
                    ];
                    file_put_contents('price_history.json', json_encode($priceHistory));
                }
            } elseif ($action === 'sell') {
                if ($user['balance'] >= $amount) {
                    $revenue = $amount * $config['price'];
                    $user['balance'] -= $amount;
                    $user['usd_balance'] += $revenue;
                    
                    // SILENT DEFLATION: Decrease price by 6%
                    $config['price'] *= 0.94;
                    
                    $transaction = [
                        'id' => uniqid(),
                        'type' => 'sell',
                        'user_id' => $_SESSION['user_id'],
                        'amount' => $amount,
                        'price' => $revenue / $amount, // Show actual price received
                        'total' => $revenue,
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                    $transactions[] = $transaction;
                    
                    // Record price change silently
                    $priceHistory = file_exists('price_history.json') ? json_decode(file_get_contents('price_history.json'), true) : [];
                    $priceHistory[] = [
                        'price' => $config['price'],
                        'type' => 'sell',
                        'timestamp' => time()
                    ];
                    file_put_contents('price_history.json', json_encode($priceHistory));
                }
            }
            break;
        }
    }
    
    file_put_contents('users.json', json_encode($users));
    file_put_contents('config.json', json_encode($config));
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
    
    header('Location: trade.php');
    exit;
}

// Get user data
$users = json_decode(file_get_contents('users.json'), true);
$currentUser = null;
foreach ($users as $user) {
    if ($user['id'] === $_SESSION['user_id']) {
        $currentUser = $user;
        break;
    }
}

// Get price history for chart
$priceHistory = file_exists('price_history.json') ? json_decode(file_get_contents('price_history.json'), true) : [];
// Ensure we have at least the current price
if (empty($priceHistory)) {
    $priceHistory[] = ['price' => $config['price'], 'timestamp' => time()];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Trade CSC</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav>
        <div class="nav-brand">CSC Platform</div>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="trade.php" class="active">Trade</a>
            <a href="wallet.php">Wallet</a>
            <a href="deposit.php">Deposit</a>
            <a href="blockchain.php">Blockchain</a>
            <span><?php echo $_SESSION['user_name']; ?></span>
            <a href="logout.php">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <h1>Trade Centennial Spartan Coin</h1>
        
        <div class="trading-container">
            <div class="price-display">
                Current Price: <span class="price">$<?php echo number_format($config['price'], 4); ?></span>
                <div class="market-status">
                    <span class="status-indicator <?php echo count($priceHistory) > 1 && end($priceHistory)['price'] > $priceHistory[count($priceHistory)-2]['price'] ? 'up' : 'down'; ?>"></span>
                    Market Active
                </div>
            </div>
            
            <div class="trading-forms">
                <!-- Buy Form -->
                <div class="trade-form buy">
                    <h3>Buy CSC</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="buy">
                        <input type="number" name="amount" step="0.01" min="0.01" placeholder="CSC Amount" required id="buyAmount">
                        <div class="calculation">
                            <small>Price: $<span id="buyPriceDisplay"><?php echo number_format($config['price'], 4); ?></span></small><br>
                            <small>Total: $<span id="buyCost">0.00</span></small>
                        </div>
                        <div class="balance">
                            Available: $<?php echo number_format($currentUser['usd_balance'], 2); ?>
                        </div>
                        <button type="submit" <?php echo $currentUser['usd_balance'] < $config['price'] * 0.01 ? 'disabled' : ''; ?>>
                            Buy CSC
                        </button>
                    </form>
                </div>
                
                <!-- Sell Form -->
                <div class="trade-form sell">
                    <h3>Sell CSC</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="sell">
                        <input type="number" name="amount" step="0.01" min="0.01" placeholder="CSC Amount" required id="sellAmount">
                        <div class="calculation">
                            <small>Price: $<span id="sellPriceDisplay"><?php echo number_format($config['price'], 4); ?></span></small><br>
                            <small>Total: $<span id="sellRevenue">0.00</span></small>
                        </div>
                        <div class="balance">
                            Available: <?php echo number_format($currentUser['balance'], 2); ?> CSC
                        </div>
                        <button type="submit" <?php echo $currentUser['balance'] < 0.01 ? 'disabled' : ''; ?>>
                            Sell CSC
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Price Chart -->
            <div class="chart-container">
                <h3>Price History</h3>
                <canvas id="priceChart"></canvas>
            </div>
        </div>
    </div>
    
    <script>
        const currentPrice = <?php echo $config['price']; ?>;
        
        // Update buy form calculations
        document.getElementById('buyAmount').addEventListener('input', function() {
            const amount = parseFloat(this.value) || 0;
            document.getElementById('buyCost').textContent = (amount * currentPrice).toFixed(2);
        });
        
        // Update sell form calculations
        document.getElementById('sellAmount').addEventListener('input', function() {
            const amount = parseFloat(this.value) || 0;
            document.getElementById('sellRevenue').textContent = (amount * currentPrice).toFixed(2);
        });
        
        // Initialize chart
        const ctx = document.getElementById('priceChart').getContext('2d');
        const priceData = <?php echo json_encode(array_column(array_slice($priceHistory, -50), 'price')); ?>;
        const labels = Array.from({length: priceData.length}, (_, i) => i + 1);
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'CSC Price',
                    data: priceData,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.1,
                    fill: true,
                    pointRadius: 0
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        display: false
                    }
                }
            }
        });
    </script>
</body>
</html>