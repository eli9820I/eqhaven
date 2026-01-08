<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

$users = json_decode(file_get_contents('users.json'), true);
$transactions = file_exists('transactions.json') ? json_decode(file_get_contents('transactions.json'), true) : [];

$currentUser = null;
foreach ($users as $user) {
    if ($user['id'] === $_SESSION['user_id']) {
        $currentUser = $user;
        break;
    }
}

// Filter user transactions
$userTransactions = array_filter($transactions, function($tx) {
    return $tx['user_id'] === $_SESSION['user_id'];
});
?>
<!DOCTYPE html>
<html>
<head>
    <title>Wallet</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav>
        <div class="nav-brand">CSC Platform</div>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="trade.php">Trade</a>
            <a href="wallet.php" class="active">Wallet</a>
            <a href="deposit.php">Deposit</a>
            <a href="blockchain.php">Blockchain</a>
            <span><?php echo $_SESSION['user_name']; ?></span>
            <a href="logout.php">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <h1>Your Wallet</h1>
        
        <div class="wallet-balance">
            <div class="balance-card">
                <h3>CSC Balance</h3>
                <div class="amount"><?php echo number_format($currentUser['balance'], 2); ?> CSC</div>
                <div class="value">$<?php echo number_format($currentUser['balance'] * json_decode(file_get_contents('config.json'), true)['price'], 2); ?></div>
            </div>
            
            <div class="balance-card">
                <h3>USD Balance</h3>
                <div class="amount">$<?php echo number_format($currentUser['usd_balance'], 2); ?></div>
                <a href="deposit.php" class="deposit-btn">Add Funds</a>
            </div>
            
            <div class="balance-card">
                <h3>CSC Address</h3>
                <div class="address"><?php echo $_SESSION['csc_address']; ?></div>
                <small>Use this address to receive CSC</small>
            </div>
        </div>
        
        <div class="transaction-history">
            <h2>Transaction History</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach(array_reverse($userTransactions) as $tx): ?>
                    <tr>
                        <td><?php echo date('Y-m-d H:i', strtotime($tx['timestamp'])); ?></td>
                        <td class="<?php echo $tx['type']; ?>"><?php echo strtoupper($tx['type']); ?></td>
                        <td><?php echo $tx['amount']; ?> CSC</td>
                        <td>$<?php echo number_format($tx['price'], 4); ?></td>
                        <td>$<?php echo number_format($tx['total'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>