<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

$config = json_decode(file_get_contents('config.json'), true);
$users = json_decode(file_get_contents('users.json'), true);
$transactions = file_exists('transactions.json') ? json_decode(file_get_contents('transactions.json'), true) : [];
$priceHistory = file_exists('price_history.json') ? json_decode(file_get_contents('price_history.json'), true) : [];

// Calculate market stats
$totalVolume = 0;
$buyCount = 0;
$sellCount = 0;

foreach ($transactions as $tx) {
    if (isset($tx['type'])) {
        if ($tx['type'] === 'buy') $buyCount++;
        if ($tx['type'] === 'sell') $sellCount++;
        if (isset($tx['total'])) $totalVolume += $tx['total'];
    }
}

// Calculate 24h price change
$price24hAgo = $config['price'];
if (count($priceHistory) > 10) {
    $price24hAgo = $priceHistory[max(0, count($priceHistory) - 10)]['price'];
}
$priceChange24h = $config['price'] - $price24hAgo;
$priceChangePercent24h = ($priceChange24h / $price24hAgo) * 100;
?>
<!DOCTYPE html>
<html>
<head>
    <title>CSC Platform</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <nav>
        <div class="nav-brand">CSC Platform</div>
        <div class="nav-links">
            <a href="index.php" class="active">Home</a>
            <a href="trade.php">Trade</a>
            <a href="wallet.php">Wallet</a>
            <a href="deposit.php">Deposit</a>
            <a href="blockchain.php">Blockchain</a>
            <span><?php echo $_SESSION['user_name']; ?></span>
            <a href="logout.php">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="market-header">
            <h1>Market Overview</h1>
            <div class="market-ticker">
                <span class="ticker-price">$<?php echo number_format($config['price'], 4); ?></span>
                <span class="ticker-change <?php echo $priceChange24h >= 0 ? 'up' : 'down'; ?>">
                    <?php echo $priceChange24h >= 0 ? '+' : ''; ?><?php echo number_format($priceChange24h, 4); ?> 
                    (<?php echo number_format($priceChangePercent24h, 2); ?>%)
                </span>
            </div>
        </div>
        
        <div class="dashboard">
            <div class="card">
                <h3>Your CSC Balance</h3>
                <?php 
                $user = null;
                foreach ($users as $u) {
                    if ($u['id'] === $_SESSION['user_id']) {
                        $user = $u;
                        break;
                    }
                }
                ?>
                <div class="balance"><?php echo number_format($user['balance'], 2); ?> CSC</div>
                <div class="value">$<?php echo number_format($user['balance'] * $config['price'], 2); ?></div>
            </div>
            
            <div class="card">
                <h3>Market Volume (24h)</h3>
                <div class="volume">$<?php echo number_format($totalVolume, 2); ?></div>
                <div class="volume-stats">
                    <span class="buy-volume"><?php echo $buyCount; ?> buys</span>
                    <span class="sell-volume"><?php echo $sellCount; ?> sells</span>
                </div>
            </div>
            
            <div class="card">
                <h3>Total Supply</h3>
                <div class="supply">100,000 CSC</div>
                <div class="supply-detail">
                    <?php 
                    $circulating = 0;
                    foreach ($users as $u) {
                        $circulating += $u['balance'];
                    }
                    ?>
                    Circulating: <?php echo number_format($circulating, 2); ?> CSC
                </div>
            </div>
        </div>
        
        <div class="chart-container">
            <h2>Price Chart</h2>
            <canvas id="priceChart"></canvas>
        </div>
        
        <div class="recent-activity">
            <h2>Recent Activity</h2>
            <div class="activity-grid">
                <?php 
                $recent = array_slice($transactions, -6);
                foreach(array_reverse($recent) as $tx): 
                    if (!isset($tx['type'])) continue;
                ?>
                <div class="activity-item <?php echo $tx['type']; ?>">
                    <div class="activity-type"><?php echo strtoupper($tx['type']); ?></div>
                    <div class="activity-details">
                        <?php if (isset($tx['amount'])): ?>
                        <div class="activity-amount"><?php echo $tx['amount']; ?> CSC</div>
                        <?php endif; ?>
                        <div class="activity-time"><?php echo date('H:i', strtotime($tx['timestamp'])); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <script>
        const ctx = document.getElementById('priceChart').getContext('2d');
        const priceData = <?php echo json_encode(array_column(array_slice($priceHistory, -30), 'price')); ?>;
        const labels = Array.from({length: priceData.length}, (_, i) => '');
        
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