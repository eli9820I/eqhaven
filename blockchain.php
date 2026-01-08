<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

$blockchain = file_exists('blockchain.json') ? json_decode(file_get_contents('blockchain.json'), true) : [];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Blockchain Explorer</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav>
        <div class="nav-brand">CSC Platform</div>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="trade.php">Trade</a>
            <a href="wallet.php">Wallet</a>
            <a href="deposit.php">Deposit</a>
            <a href="blockchain.php" class="active">Blockchain</a>
            <span><?php echo $_SESSION['user_name']; ?></span>
            <a href="logout.php">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <h1>Blockchain Explorer</h1>
        
        <div class="blockchain-stats">
            <div class="stat">
                <h3>Blocks</h3>
                <div class="value"><?php echo count($blockchain); ?></div>
            </div>
            <div class="stat">
                <h3>Chain Valid</h3>
                <div class="value valid">✓</div>
            </div>
            <div class="stat">
                <h3>Last Block</h3>
                <div class="value"><?php echo date('H:i:s', end($blockchain)['timestamp'] ?? time()); ?></div>
            </div>
        </div>
        
        <div class="blocks-list">
            <?php foreach(array_reverse($blockchain) as $block): ?>
            <div class="block">
                <div class="block-header">
                    <span class="block-number">Block #<?php echo $block['index']; ?></span>
                    <span class="block-time"><?php echo date('Y-m-d H:i:s', $block['timestamp']); ?></span>
                </div>
                <div class="block-hash">
                    Hash: <?php echo substr($block['hash'], 0, 20) . '...'; ?>
                </div>
                <div class="block-prev">
                    Prev: <?php echo substr($block['previous_hash'], 0, 20) . '...'; ?>
                </div>
                <div class="block-transactions">
                    Transactions: <?php echo count($block['transactions']); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>