<?php
session_start();

// --- WATCHLIST INITIALIZATION ---
if (!isset($_SESSION['watchlist'])) {
    $_SESSION['watchlist'] = [];
}

// --- ADD TO WATCHLIST ---
function addToWatchlist($symbol) {
    $symbol = strtoupper(trim($symbol));
    if ($symbol !== '' && !in_array($symbol, $_SESSION['watchlist'])) {
        $_SESSION['watchlist'][] = $symbol;
    }
}

// --- REMOVE FROM WATCHLIST ---
function removeFromWatchlist($symbol) {
    $symbol = strtoupper(trim($symbol));
    if (($idx = array_search($symbol, $_SESSION['watchlist'])) !== false) {
        array_splice($_SESSION['watchlist'], $idx, 1);
    }
}

// --- CSV LOOKUP ---
function getInstrumentKeyByName($stockName, $file = 'output.csv') {
    if (!file_exists($file)) return false;
    $handle = fopen($file, 'r');
    if ($handle === false) return false;
    fgetcsv($handle); // skip header
    while (($data = fgetcsv($handle)) !== false) {
        if (strtoupper(trim($data[0])) === strtoupper(trim($stockName))) {
            fclose($handle);
            return trim($data[1]); // Instrument key
        }
    }
    fclose($handle);
    return false;
}

// --- FETCH LIVE PRICE ---
function fetchLivePrice($instrumentKey) {
    if (!$instrumentKey) return false;
    $accessToken = 'eyJ0eXAiOiJKV1QiLCJrZXlfaWQiOiJza192MS4wIiwiYWxnIjoiSFMyNTYifQ.eyJzdWIiOiI4SEEyQzQiLCJqdGkiOiI2ODhjOWYzY2JmOWFmOTI5NjFkNTcyYjYiLCJpc011bHRpQ2xpZW50IjpmYWxzZSwiaXNQbHVzUGxhbiI6ZmFsc2UsImlhdCI6MTc1NDA0NjI2OCwiaXNzIjoidWRhcGktZ2F0ZXdheS1zZXJ2aWNlIiwiZXhwIjoxNzU0MDg1NjAwfQ.cLoagstbF10oVrVxJsncojU8YjO5b4q_kly3dhp6zBA'; // <-- USE YOUR TOKEN
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.upstox.com/v2/market-quote/ltp?instrument_key=$instrumentKey",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Accept: application/json",
            "Authorization: Bearer $accessToken"
        ],
        CURLOPT_TIMEOUT => 4,
    ]);
    $response = curl_exec($curl);
    curl_close($curl);
    $data = json_decode($response, true);
    $priceData = $data['data'] ?? [];
    if (!empty($priceData)) {
        foreach ($priceData as $info) {
            if (isset($info['last_price'])) return $info['last_price'];
        }
    }
    return false;
}

// --- HANDLE FORM/GETS ---
$selectedStock = '';
$livePriceOutput = '';
$errorOutput = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // If deleting, handle delete
    if (!empty($_POST['delete_symbol'])) {
        removeFromWatchlist($_POST['delete_symbol']);
    }
    // If adding/fetching, handle add
    else if (!empty($_POST['stock_name'])) {
        $stockName = strtoupper(trim($_POST['stock_name']));
        addToWatchlist($stockName);
        $instrumentKey = getInstrumentKeyByName($stockName);
        if (!$instrumentKey) {
            $errorOutput = "❌ Instrument key not found for <strong>$stockName</strong>";
        } else {
            $ltp = fetchLivePrice($instrumentKey);
            if ($ltp === false) $errorOutput = "⚠️ Price data not available for $stockName.";
            else $livePriceOutput = "📈 Live for <strong>$stockName</strong>: ₹$ltp";
        }
        $selectedStock = $stockName;
    }
}
// Selecting a different symbol from GET
if (!$selectedStock && isset($_GET['symbol'])) {
    $selectedStock = strtoupper(trim($_GET['symbol']));
}

// Fallbacks
if (!$selectedStock && !empty($_SESSION['watchlist'])) {
    $selectedStock = $_SESSION['watchlist'][0];
} elseif (!$selectedStock) {
    $selectedStock = 'SBIN';
}

// Fetch all prices for watchlist
$watchlistPrices = [];
foreach ($_SESSION['watchlist'] as $symbol) {
    $instrumentKey = getInstrumentKeyByName($symbol);
    $watchlistPrices[$symbol] = $instrumentKey ? fetchLivePrice($instrumentKey) : false;
}
$selectedInstrumentKey = getInstrumentKeyByName($selectedStock);
$selectedStockPrice = $selectedInstrumentKey ? fetchLivePrice($selectedInstrumentKey) : false;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upstox - Stock Trading Platform</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --upstox-primary: #ff5722;
            --upstox-primary-dark: #e64a19;
            --upstox-secondary: #2962ff;
            --upstox-green: #00c853;
            --upstox-red: #ff1744;
            --upstox-bg: #f5f7fa;
            --upstox-card: #ffffff;
            --upstox-text: #263238;
            --upstox-text-light: #607d8b;
            --upstox-border: #eceff1;
            --upstox-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--upstox-bg);
            color: var(--upstox-text);
            display: flex;
            height: 100vh;
            overflow: hidden;
        }
        /* Header */
        .header {
            height: 60px;
            background: var(--upstox-card);
            border-bottom: 1px solid var(--upstox-border);
            display: flex;
            align-items: center;
            padding: 0 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
        }
        .logo {
            font-size: 24px;
            font-weight: 700;
            color: var(--upstox-primary);
            margin-right: 40px;
        }
        /* Sidebar */
        #sidebar {
            width: 280px;
            background: var(--upstox-card);
            border-right: 1px solid var(--upstox-border);
            padding: 80px 0 20px;
            height: 100vh;
            overflow-y: auto;
            box-shadow: var(--upstox-shadow);
            z-index: 10;
        }
        /* Main Content */
        #main {
            flex: 1;
            padding: 80px 24px 24px;
            min-width: 0;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        /* Search Form */
        #search-form {
            padding: 0 20px 20px;
            position: relative;
        }
        .search-container {
            position: relative;
        }
        #stock_name {
            width: 100%;
            padding: 12px 12px 12px 40px;
            font-size: 14px;
            border-radius: 8px;
            border: 1px solid var(--upstox-border);
            background: #f5f7fa;
            transition: all 0.2s;
        }
        #stock_name:focus {
            outline: none;
            border-color: var(--upstox-secondary);
            background: white;
            box-shadow: 0 0 0 3px rgba(41, 98, 255, 0.1);
        }
        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--upstox-text-light);
        }
        #search-form button {
            width: 100%;
            padding: 12px;
            margin-top: 12px;
            background: var(--upstox-primary);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        #search-form button:hover {
            background: var(--upstox-primary-dark);
        }
        #suggestions {
            position: absolute;
            left: 20px;
            right: 20px;
            top: 45px;
            max-height: 200px;
            overflow-y: auto;
            background: white;
            border-radius: 0 0 8px 8px;
            border: 1px solid var(--upstox-border);
            border-top: none;
            z-index: 50;
            box-shadow: var(--upstox-shadow);
        }
        #suggestions div {
            padding: 10px 15px;
            cursor: pointer;
            transition: background 0.12s;
            font-size: 14px;
        }
        #suggestions div:hover {
            background: #f0f4ff;
        }
        /* Section Headings */
        .section-title {
            padding: 0 20px 10px;
            font-size: 16px;
            font-weight: 600;
            color: var(--upstox-text);
            display: flex;
            align-items: center;
        }
        .section-title i {
            margin-right: 8px;
            color: var(--upstox-primary);
        }
        /* Watchlist */
        #watchlist {
            list-style: none;
            padding: 0 10px;
        }
        #watchlist li {
            background: var(--upstox-card);
            margin-bottom: 8px;
            border-radius: 8px;
            border: 1px solid var(--upstox-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 500;
            padding: 12px 15px;
            transition: all 0.2s;
        }
        #watchlist li.active, #watchlist li:hover {
            background: #f0f4ff !important;
            border-color: var(--upstox-secondary);
            box-shadow: 0 2px 8px rgba(41, 98, 255, 0.1);
        }
        #watchlist a {
            text-decoration: none;
            color: inherit;
            flex-grow: 1;
            font-size: 14px;
            display: flex;
            align-items: center;
        }
        #watchlist a i {
            margin-right: 8px;
            color: var(--upstox-secondary);
            font-size: 16px;
        }
        .price {
            font-size: 14px;
            font-weight: 600;
            color: var(--upstox-green);
            text-align: right;
            min-width: 70px;
        }
        .price.down {
            color: var(--upstox-red);
        }
        .btn-delete {
            background: none;
            border: none;
            color: #9e9e9e;
            margin-left: 10px;
            cursor: pointer;
            padding: 5px;
            border-radius: 4px;
            transition: all 0.2s;
        }
        .btn-delete:hover {
            color: var(--upstox-red);
            background: rgba(255, 23, 68, 0.1);
        }
        /* Stock Info */
        #stock-info {
            background: var(--upstox-card);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--upstox-shadow);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
        }
        .stock-details {
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        #stock-name {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }
        #stock-name i {
            margin-right: 10px;
            color: var(--upstox-secondary);
        }
        .stock-exchange {
            font-size: 14px;
            color: var(--upstox-text-light);
            margin-bottom: 8px;
        }
        #stock-price {
            font-size: 28px;
            font-weight: 700;
            color: var(--upstox-green);
            display: flex;
            align-items: center;
            margin-top: 5px;
            margin-bottom: 10px;
        }
        #stock-price.down {
            color: var(--upstox-red);
        }
        #stock-price .change {
            font-size: 14px;
            margin-left: 10px;
            padding: 4px 8px;
            border-radius: 4px;
            background: rgba(0, 200, 83, 0.1);
        }
        #stock-price.down .change {
            background: rgba(255, 23, 68, 0.1);
        }
        #stock-error {
            font-size: 16px;
            color: var(--upstox-red);
            font-weight: 500;
        }
        #action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        #action-buttons button {
            padding: 10px 24px;
            font-size: 14px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #action-buttons button i {
            margin-right: 8px;
        }
        #btn-buy {
            background: var(--upstox-green);
            color: white;
        }
        #btn-buy:hover {
            background: #00b34a;
            box-shadow: 0 2px 8px rgba(0, 200, 83, 0.3);
        }
        #btn-sell {
            background: var(--upstox-red);
            color: white;
        }
        #btn-sell:hover {
            background: #e3002e;
            box-shadow: 0 2px 8px rgba(255, 23, 68, 0.3);
        }
        /* Navigation Tabs */
        .nav-tabs {
            display: flex;
            margin-bottom: 20px;
            background: var(--upstox-card);
            border-radius: 12px 12px 0 0;
            overflow-x: auto;
            box-shadow: var(--upstox-shadow);
        }
        
        .nav-tab {
            padding: 15px 20px;
            font-size: 14px;
            font-weight: 600;
            color: var(--upstox-text-light);
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            white-space: nowrap;
        }
        
        .nav-tab i {
            margin-right: 8px;
        }
        
        .nav-tab:hover {
            color: var(--upstox-secondary);
        }
        
        .nav-tab.active {
            color: var(--upstox-secondary);
            border-bottom-color: var(--upstox-secondary);
        }
        
        /* Tab Content Container */
        .tab-content-container {
            flex: 1;
            min-height: 0;
            position: relative;
            display: flex;
            flex-direction: column;
        }
        
        .tab-content {
            display: none;
            flex: 1;
            min-height: 400px;
            background: var(--upstox-card);
            border-radius: 0 0 12px 12px;
            overflow: hidden;
            box-shadow: var(--upstox-shadow);
            flex-direction: column;
            position: relative;
        }
        
        .tab-content.active {
            display: flex;
        }
        
        /* Chart Container */
        #chart-container {
            flex: 1;
            min-height: 0;
            background: var(--upstox-card);
            border-radius: 0 0 12px 12px;
            overflow: hidden;
            box-shadow: var(--upstox-shadow);
            display: flex;
            flex-direction: column;
            position: relative;
        }
        
        /* API Content Containers */
        .api-content-container {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .loading-indicator {
            font-size: 16px;
            color: var(--upstox-text-light);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .loading-indicator i {
            color: var(--upstox-secondary);
        }
        
        /* Tab Content Styling */
        .error-message {
            color: var(--upstox-red);
            font-size: 16px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .api-note {
            color: var(--upstox-text-light);
            font-size: 14px;
            font-style: italic;
        }
        
        .no-data {
            color: var(--upstox-text-light);
            font-size: 16px;
            text-align: center;
            padding: 30px;
        }
        
        /* Table Styling */
        .option-chain-table, .holdings-table {
            width: 100%;
            overflow-x: auto;
        }
        
        .option-chain-table table, .holdings-table table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        .option-chain-table th, .holdings-table th {
            background: #f5f7fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: var(--upstox-text);
            border-bottom: 1px solid var(--upstox-border);
        }
        
        .option-chain-table td, .holdings-table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--upstox-border);
        }
        
        .option-chain-table tr:hover, .holdings-table tr:hover {
            background: #f0f4ff;
        }
        
        /* Info and Fundamentals Styling */
        .info-grid, .fundamentals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            width: 100%;
        }
        
        .info-section, .fundamentals-section {
            background: #f9fafc;
            border-radius: 8px;
            padding: 15px;
            border: 1px solid var(--upstox-border);
        }
        
        .info-section h3, .fundamentals-section h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--upstox-secondary);
            border-bottom: 1px solid var(--upstox-border);
            padding-bottom: 8px;
        }
        
        .info-row, .fundamentals-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .info-row:last-child, .fundamentals-row:last-child {
            border-bottom: none;
        }
        
        .info-row span:first-child, .fundamentals-row span:first-child {
            font-weight: 500;
            color: var(--upstox-text);
        }
        
        .info-row span:last-child, .fundamentals-row span:last-child {
            font-weight: 600;
        }
        
        .positive {
            color: var(--upstox-green);
        }
        
        .negative {
            color: var(--upstox-red);
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .nav-tab {
                padding: 12px 15px;
                font-size: 13px;
            }
            
            .info-grid, .fundamentals-grid {
                grid-template-columns: 1fr;
            }
        }
        
        #tv-chart-container {
            position: relative;
        }
        
        #tv-chart-container.loading::before {
            content: "Loading chart data...";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: var(--upstox-secondary);
            z-index: 10;
        }
        /* Chart Controls */
        .chart-controls {
            display: flex;
            padding: 10px 20px;
            border-bottom: 1px solid var(--upstox-border);
            background: var(--upstox-card);
        }
        .timeframe-selector {
            display: flex;
            gap: 5px;
        }
        .timeframe-btn {
            padding: 6px 12px;
            font-size: 12px;
            border: 1px solid var(--upstox-border);
            background: var(--upstox-bg);
            color: var(--upstox-text);
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .timeframe-btn.active {
            background: var(--upstox-secondary);
            color: white;
            border-color: var(--upstox-secondary);
        }
        .timeframe-btn:hover:not(.active) {
            background: #e8eaed;
        }
        /* Responsive */
        @media (max-width: 768px) {
            #sidebar {
                width: 240px;
            }
            #stock-name {
                font-size: 20px;
            }
            #stock-price {
                font-size: 24px;
            }
            #action-buttons button {
                padding: 8px 16px;
            }
        }
        @media (max-width: 576px) {
            body {
                flex-direction: column;
            }
            #sidebar {
                width: 100%;
                height: auto;
                max-height: 40vh;
                padding-top: 70px;
            }
            #main {
                padding-top: 20px;
            }
            #stock-info {
                flex-direction: column;
                align-items: flex-start;
            }
            #action-buttons {
                margin-top: 15px;
                width: 100%;
            }
            #action-buttons button {
                flex: 1;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="logo">Upstox</div>
    </div>
    
    <div id="sidebar">
        <div class="section-title">
            <i class="fas fa-search"></i> Search Stock
        </div>
        <form id="search-form" method="POST" autocomplete="off">
            <div class="search-container">
                <i class="fas fa-search search-icon"></i>
                <input type="text" name="stock_name" id="stock_name" placeholder="e.g., SBIN" required autocomplete="off" onkeyup="searchStock()" />
                <div id="suggestions"></div>
            </div>
            <button type="submit"><i class="fas fa-plus"></i> Add / Get Price</button>
        </form>

        <div class="section-title">
            <i class="fas fa-star"></i> Watchlist
        </div>
        <ul id="watchlist">
        <?php foreach ($_SESSION['watchlist'] as $symbol): 
            $activeClass = ($symbol === $selectedStock) ? 'active' : '';
            $price = $watchlistPrices[$symbol];
            $priceFormatted = ($price !== false && $price !== null) ? '₹'.$price : "--";
        ?>
            <li class="<?= $activeClass ?>">
                <a href="?symbol=<?= htmlspecialchars($symbol) ?>"
                   <?php if ($activeClass) echo 'style="font-weight:700;color:#355adb;"'; ?>>
                    <i class="fas fa-chart-line"></i> <?= htmlspecialchars($symbol) ?>
                </a>
                <span class="price"><?= $priceFormatted ?></span>
                <form method="post" action="" style="margin: 0; display: inline;">
                    <input type="hidden" name="delete_symbol" value="<?= htmlspecialchars($symbol) ?>">
                    <button type="submit" class="btn-delete" title="Remove <?= htmlspecialchars($symbol) ?>"><i class="fas fa-times"></i></button>
                </form>
            </li>
        <?php endforeach; ?>
        </ul>
    </div>
    <div id="main">
        <div id="stock-info" aria-live="polite">
            <div class="stock-details">
                <div id="stock-name"><i class="fas fa-chart-line"></i> <?= htmlspecialchars($selectedStock) ?></div>
                <div class="stock-exchange">NSE Symbol (BSE Chart)</div>
                <?php if ($selectedStockPrice !== false): ?>
                    <div id="stock-price">₹<?= htmlspecialchars($selectedStockPrice) ?></div>
                <?php elseif ($errorOutput): ?>
                    <div id="stock-error"><?= $errorOutput ?></div>
                <?php else: ?>
                    <div id="stock-price">--</div>
                <?php endif; ?>
            </div>
            <div id="action-buttons">
                <button id="btn-buy" type="button"><i class="fas fa-arrow-up"></i> Buy</button>
                <button id="btn-sell" type="button"><i class="fas fa-arrow-down"></i> Sell</button>
            </div>
        </div>
        
        <!-- Navigation Tabs -->
        <div class="nav-tabs">
            <button class="nav-tab active" data-tab="chart"><i class="fas fa-chart-line"></i> Chart</button>
            <button class="nav-tab" data-tab="option-chain"><i class="fas fa-layer-group"></i> Option Chain</button>
            <button class="nav-tab" data-tab="info"><i class="fas fa-info-circle"></i> Info</button>
            <button class="nav-tab" data-tab="holdings"><i class="fas fa-briefcase"></i> Holdings</button>
            <button class="nav-tab" data-tab="fundamentals"><i class="fas fa-chart-bar"></i> Fundamentals</button>
        </div>
        
        <!-- Tab Content Containers -->
        <div class="tab-content-container">
            <!-- Chart Tab (Default Active) -->
            <div id="chart-container" class="tab-content active" data-tab-content="chart">
            <div class="chart-controls">
                <div class="timeframe-selector">
                    <button class="timeframe-btn" data-interval="1">1m</button>
                    <button class="timeframe-btn" data-interval="5">5m</button>
                    <button class="timeframe-btn" data-interval="15">15m</button>
                    <button class="timeframe-btn active" data-interval="D">1D</button>
                    <button class="timeframe-btn" data-interval="W">1W</button>
                    <button class="timeframe-btn" data-interval="M">1M</button>
                </div>
                <button id="toggle-chart-btn" class="chart-toggle-btn" onclick="toggleChartImplementation()">Switch to Real-time Chart</button>
            </div>
            <div id="tv-chart-container" style="height: calc(100% - 50px);"></div>
        </div>
        
        <!-- Option Chain Tab -->
        <div class="tab-content" data-tab-content="option-chain">
            <div class="api-content-container">
                <div class="loading-indicator"><i class="fas fa-spinner fa-spin"></i> Loading option chain data...</div>
                <div class="option-chain-content"></div>
            </div>
        </div>
        
        <!-- Info Tab -->
        <div class="tab-content" data-tab-content="info">
            <div class="api-content-container">
                <div class="loading-indicator"><i class="fas fa-spinner fa-spin"></i> Loading stock information...</div>
                <div class="info-content"></div>
            </div>
        </div>
        
        <!-- Holdings Tab -->
        <div class="tab-content" data-tab-content="holdings">
            <div class="api-content-container">
                <div class="loading-indicator"><i class="fas fa-spinner fa-spin"></i> Loading holdings data...</div>
                <div class="holdings-content"></div>
            </div>
        </div>
        
        <!-- Fundamentals Tab -->
        <div class="tab-content" data-tab-content="fundamentals">
            <div class="api-content-container">
                <div class="loading-indicator"><i class="fas fa-spinner fa-spin"></i> Loading fundamental data...</div>
                <div class="fundamentals-content"></div>
            </div>
        </div>
        
        </div> <!-- End of tab-content-container -->
    </div> <!-- End of main -->

    <!-- TradingView Charting Library -->
    <script src="https://s3.tradingview.com/tv.js"></script>
    <!-- TradingView Lightweight Charts -->
    <script src="https://unpkg.com/lightweight-charts/dist/lightweight-charts.standalone.production.js"></script>
    <link rel="stylesheet" href="lightweight-charts.css">
    <script src="lightweight-charts.js"></script>
    <script>
        // Global variables
        const chartContainerId = "tv-chart-container";
        let currentSymbol = "<?= htmlspecialchars($selectedStock) ?>";
        let currentInterval = "D";
        let widget = null;
        let useTraditionalChart = true; // Flag to toggle between chart implementations
        
        // Format symbol for display
        function formatSymbolDisplay(symbol) {
            return symbol.replace(/^NSE:|^BSE:/, '');
        }
        
        // Format symbol for TradingView
        function formatSymbolForTV(symbol) {
            // If it's already a formatted symbol with exchange prefix, return as is
            if (symbol.includes(':')) return symbol;
            
            // For NSE symbols, use BSE equivalent for TradingView chart
            // since TradingView doesn't provide charts for all NSE symbols
            if (/^[A-Z0-9.]+$/.test(symbol)) return "BSE:" + symbol;
            return symbol;
        }
        
        // Initialize and render chart
        // Initialize and render chart
        function initChart(symbol, interval) {
            if (useTraditionalChart) {
                initTraditionalChart(symbol, interval);
            } else {
                // Get access token and instrument key from PHP
                const accessToken = '<?= $accessToken ?? "eyJ0eXAiOiJKV1QiLCJrZXlfaWQiOiJza192MS4wIiwiYWxnIjoiSFMyNTYifQ.eyJzdWIiOiI4SEEyQzQiLCJqdGkiOiI2ODhjOWYzY2JmOWFmOTI5NjFkNTcyYjYiLCJpc011bHRpQ2xpZW50IjpmYWxzZSwiaXNQbHVzUGxhbiI6ZmFsc2UsImlhdCI6MTc1NDA0NjI2OCwiaXNzIjoidWRhcGktZ2F0ZXdheS1zZXJ2aWNlIiwiZXhwIjoxNzU0MDg1NjAwfQ.cLoagstbF10oVrVxJsncojU8YjO5b4q_kly3dhp6zBA" ?>';
                const instrumentKey = '<?= $selectedInstrumentKey ?>';
                
                // Initialize lightweight chart
                LightweightCharts.initChart(chartContainerId, symbol, interval, accessToken, instrumentKey);
            }
        }
        
        // Initialize traditional TradingView chart
        function initTraditionalChart(symbol, interval) {
            if (widget) {
                widget = null;
                document.getElementById(chartContainerId).innerHTML = '';
            }
            
            // Format symbol for TradingView
            const formattedSymbol = formatSymbolForTV(symbol || "RELIANCE");
            
            // Map interval to TradingView format if needed
            const tvInterval = interval || "D";
            
            // Create TradingView widget
            widget = new TradingView.widget({
                width: "100%",
                height: "100%",
                symbol: formattedSymbol,
                interval: tvInterval,
                timezone: "Asia/Kolkata",
                theme: "light",
                style: "1",
                locale: "en",
                toolbar_bg: "#f1f3f6",
                enable_publishing: false,
                hide_top_toolbar: false,
                hide_legend: false,
                save_image: true,
                container_id: chartContainerId,
                studies: [
                    "Volume@tv-basicstudies",
                    "MACD@tv-basicstudies",
                    "RSI@tv-basicstudies"
                ],
                disabled_features: [
                    "use_localstorage_for_settings"
                ],
                enabled_features: [
                    "create_volume_indicator_by_default"
                ],
                withdateranges: true,
                details: true,
                hotlist: true,
                calendar: true,
                overrides: {
                    "mainSeriesProperties.candleStyle.upColor": "#00c853",
                    "mainSeriesProperties.candleStyle.downColor": "#ff1744",
                    "mainSeriesProperties.candleStyle.borderUpColor": "#00c853",
                    "mainSeriesProperties.candleStyle.borderDownColor": "#ff1744",
                    "mainSeriesProperties.candleStyle.wickUpColor": "#00c853",
                    "mainSeriesProperties.candleStyle.wickDownColor": "#ff1744",
                }
            });
            
            return widget;
        }
         function updateChartInterval(interval) {
             currentInterval = interval;
             
             // Update UI
             document.querySelectorAll('.timeframe-btn').forEach(btn => {
                 btn.classList.toggle('active', btn.dataset.interval === interval);
             });
             
             if (useTraditionalChart) {
                 // Initialize new chart with updated interval
                 initChart(currentSymbol, interval);
             } else {
                 // Update lightweight chart interval
                 LightweightCharts.updateInterval(interval);
             }
         }
         
         // Show chart for a symbol
         function showChart(symbol) {
             currentSymbol = symbol;
             
             // Update UI
             highlightActiveWatchlist(symbol);
             document.getElementById('stock-name').innerHTML = `<i class="fas fa-chart-line"></i> ${formatSymbolDisplay(symbol)}`;
             
             // Get instrument key
             const instrumentKey = '<?= $selectedInstrumentKey ?>';
             
             if (useTraditionalChart) {
                 // Update exchange info to show we're using BSE chart for NSE symbol
                 document.querySelector('.stock-exchange').textContent = 'NSE Symbol (BSE Chart)';
                 
                 // Show loading state
                 document.getElementById(chartContainerId).classList.add('loading');
                 
                 try {
                     // Initialize chart with current interval
                     initChart(symbol, currentInterval);
                     
                     // Get the current price from PHP variable
                     const currentPrice = '<?= htmlspecialchars($selectedStockPrice ?: '--') ?>';
                     
                     // Update price display
                     const priceElement = document.getElementById('stock-price');
                     if (priceElement) {
                         priceElement.innerHTML = `₹${currentPrice}`;
                     }

                 } catch (error) {
                     console.error('Error showing chart:', error);
                 } finally {
                     // Hide loading state after a delay
                     setTimeout(() => {
                         document.getElementById(chartContainerId).classList.remove('loading');
                     }, 1500);
                 }
             } else {
                 // Update exchange info to show we're using real-time data
                 document.querySelector('.stock-exchange').textContent = 'NSE Symbol (Real-time Data)';
                 
                 // Show lightweight chart with real-time data
                 LightweightCharts.showChart(symbol, instrumentKey);
             }
         }
         
         // Toggle between chart implementations
         function toggleChartImplementation() {
             useTraditionalChart = !useTraditionalChart;
             
             // Update UI
             const toggleBtn = document.getElementById('toggle-chart-btn');
             if (toggleBtn) {
                 toggleBtn.textContent = useTraditionalChart ? 'Switch to Real-time Chart' : 'Switch to TradingView Chart';
             }
             
             // Reinitialize chart
             showChart(currentSymbol);
         }
         
         // Highlight active watchlist item
         function highlightActiveWatchlist(symbol) {
             document.querySelectorAll('#watchlist li').forEach(li => {
                 const symbolText = li.dataset.symbol;
                 li.classList.toggle('active', symbolText === symbol);
             });
         }
         
         // Autocomplete search
         async function searchStock() {
             const input = document.getElementById('stock_name');
             const query = input.value.trim();
             const suggestionsBox = document.getElementById('suggestions');
             
             if (query.length < 1) {
                 suggestionsBox.innerHTML = '';
                 return;
             }
             
             try {
                 const response = await fetch('suggest.php?q=' + encodeURIComponent(query));
                 if (!response.ok) throw new Error('Network error');
                 
                 const data = await response.json();
                 suggestionsBox.innerHTML = '';
                 
                 data.forEach(item => {
                     const div = document.createElement('div');
                     div.textContent = item;
                     div.onclick = () => {
                         input.value = item.split(' - ')[0];
                         suggestionsBox.innerHTML = '';
                     };
                     suggestionsBox.appendChild(div);
                 });
             } catch(e) { 
                 console.error('Error in search:', e);
                 suggestionsBox.innerHTML = ''; 
             }
         }
         
         // Tab switching functionality
         function switchTab(tabName) {
             // Hide all tab contents
             document.querySelectorAll('.tab-content').forEach(content => {
                 content.classList.remove('active');
             });
             
             // Deactivate all tabs
             document.querySelectorAll('.nav-tab').forEach(tab => {
                 tab.classList.remove('active');
             });
             
             // Activate selected tab and content
             document.querySelector(`.nav-tab[data-tab="${tabName}"]`).classList.add('active');
             document.querySelector(`.tab-content[data-tab-content="${tabName}"]`).classList.add('active');
             
             // Load data for the selected tab if needed
             loadTabData(tabName, currentSymbol);
         }
         
         // Load data for tabs from Upstox API
         function loadTabData(tabName, symbol) {
             if (tabName === 'chart') return; // Chart is handled separately
             
             const contentContainer = document.querySelector(`.${tabName}-content`);
             const loadingIndicator = contentContainer.previousElementSibling;
             
             // Show loading indicator
             loadingIndicator.style.display = 'flex';
             contentContainer.innerHTML = '';
             
             // Get access token from PHP variable
             const accessToken = '<?= $accessToken ?? "eyJ0eXAiOiJKV1QiLCJrZXlfaWQiOiJza192MS4wIiwiYWxnIjoiSFMyNTYifQ.eyJzdWIiOiI4SEEyQzQiLCJqdGkiOiI2ODhjOWYzY2JmOWFmOTI5NjFkNTcyYjYiLCJpc011bHRpQ2xpZW50IjpmYWxzZSwiaXNQbHVzUGxhbiI6ZmFsc2UsImlhdCI6MTc1NDA0NjI2OCwiaXNzIjoidWRhcGktZ2F0ZXdheS1zZXJ2aWNlIiwiZXhwIjoxNzU0MDg1NjAwfQ.cLoagstbF10oVrVxJsncojU8YjO5b4q_kly3dhp6zBA" ?>';
             
             // Get instrument key
             const instrumentKey = '<?= $selectedInstrumentKey ?>';
             if (!instrumentKey) {
                 contentContainer.innerHTML = `<div class="error-message">Instrument key not found for ${symbol}</div>`;
                 loadingIndicator.style.display = 'none';
                 return;
             }
             
             // Define API endpoints based on tab
             let apiUrl = '';
             switch(tabName) {
                 // --- In loadTabData ---
case 'option-chain':
    apiUrl = `https://api.upstox.com/v2/option/chain?instrument_key=${instrumentKey}`;
    break;

// --- In displayTabData ---
case 'option-chain':
    if (data.data && Array.isArray(data.data.option_chain)) {
        html = '<div class="option-chain-table"><table>';
        html += '<thead><tr><th>Strike</th><th>Call OI</th><th>Call LTP</th><th>Put LTP</th><th>Put OI</th></tr></thead><tbody>';
        data.data.option_chain.forEach(option => {
            html += `<tr>
                <td>${option.strike_price || '-'}</td>
                <td>${option.CE?.open_interest ?? '-'}</td>
                <td>${option.CE?.last_price ?? '-'}</td>
                <td>${option.PE?.last_price ?? '-'}</td>
                <td>${option.PE?.open_interest ?? '-'}</td>
            </tr>`;
        });
        html += '</tbody></table></div>';
    } else {
        html = '<div class="no-data">No option chain data available</div>';
    }
    break;

                 case 'info':
                     apiUrl = `https://api.upstox.com/v2/market-quote/quotes?instrument_key=${instrumentKey}`;
                     break;
                 case 'holdings':
                     apiUrl = 'https://api.upstox.com/v2/portfolio/holdings';
                     break;
                 case 'fundamentals':
                     apiUrl = `https://api.upstox.com/v2/market-quote/fundamentals/${instrumentKey}`;
                     break;
                 default:
                     return;
             }
             
             // Fetch data from API
             fetch(apiUrl, {
                 headers: {
                     'Accept': 'application/json',
                     'Authorization': `Bearer ${accessToken}`
                 }
             })
             .then(response => {
                 if (!response.ok) {
                     throw new Error(`API Error: ${response.status}`);
                 }
                 return response.json();
             })
             .then(data => {
                 // Process and display data based on tab
                 displayTabData(tabName, data, contentContainer);
             })
             .catch(error => {
                 console.error(`Error fetching ${tabName} data:`, error);
                 contentContainer.innerHTML = `
                     <div class="error-message">
                         <i class="fas fa-exclamation-circle"></i>
                         Error loading data: ${error.message}
                     </div>
                     <div class="api-note">
                         Note: This feature requires a valid Upstox API key with appropriate permissions.
                     </div>
                 `;
             })
             .finally(() => {
                 // Hide loading indicator
                 loadingIndicator.style.display = 'none';
             });
         }
         
         // Display data for each tab
         function displayTabData(tabName, data, container) {
             let html = '';
             
             switch(tabName) {
                 case 'option-chain':
                     if (data.data && Array.isArray(data.data)) {
                         html = '<div class="option-chain-table"><table>';
                         html += '<thead><tr><th>Strike</th><th>Call OI</th><th>Call LTP</th><th>Put LTP</th><th>Put OI</th></tr></thead><tbody>';
                         
                         data.data.forEach(option => {
                             html += `<tr>
                                 <td>${option.strike_price || '-'}</td>
                                 <td>${option.call_oi || '-'}</td>
                                 <td>${option.call_ltp || '-'}</td>
                                 <td>${option.put_ltp || '-'}</td>
                                 <td>${option.put_oi || '-'}</td>
                             </tr>`;
                         });
                         
                         html += '</tbody></table></div>';
                     } else {
                         html = '<div class="no-data">No option chain data available</div>';
                     }
                     break;
                     
                 case 'info':
                     if (data.data && Object.keys(data.data).length > 0) {
                         const quote = data.data[Object.keys(data.data)[0]];
                         html = '<div class="info-grid">';
                         
                         // Basic info
                         html += '<div class="info-section"><h3>Basic Information</h3>';
                         html += `<div class="info-row"><span>Symbol:</span> <span>${quote.symbol || '-'}</span></div>`;
                         html += `<div class="info-row"><span>Company:</span> <span>${quote.company_name || '-'}</span></div>`;
                         html += `<div class="info-row"><span>ISIN:</span> <span>${quote.isin || '-'}</span></div>`;
                         html += `<div class="info-row"><span>Sector:</span> <span>${quote.sector || '-'}</span></div>`;
                         html += '</div>';
                         
                         // Price info
                         html += '<div class="info-section"><h3>Price Information</h3>';
                         html += `<div class="info-row"><span>LTP:</span> <span>₹${quote.last_price || '-'}</span></div>`;
                         html += `<div class="info-row"><span>Change:</span> <span class="${quote.net_change >= 0 ? 'positive' : 'negative'}">₹${quote.net_change || '-'} (${quote.net_change_percentage || '-'}%)</span></div>`;
                         html += `<div class="info-row"><span>Open:</span> <span>₹${quote.open_price || '-'}</span></div>`;
                         html += `<div class="info-row"><span>High:</span> <span>₹${quote.high_price || '-'}</span></div>`;
                         html += `<div class="info-row"><span>Low:</span> <span>₹${quote.low_price || '-'}</span></div>`;
                         html += `<div class="info-row"><span>Close:</span> <span>₹${quote.close_price || '-'}</span></div>`;
                         html += `<div class="info-row"><span>Volume:</span> <span>${quote.volume || '-'}</span></div>`;
                         html += '</div>';
                         
                         html += '</div>'; // Close info-grid
                     } else {
                         html = '<div class="no-data">No information available</div>';
                     }
                     break;
                     
                 case 'holdings':
                     if (data.data && Array.isArray(data.data.holdings)) {
                         html = '<div class="holdings-table"><table>';
                         html += '<thead><tr><th>Symbol</th><th>Quantity</th><th>Avg Price</th><th>LTP</th><th>P&L</th></tr></thead><tbody>';
                         
                         data.data.holdings.forEach(holding => {
                             const pnl = holding.pnl || 0;
                             html += `<tr>
                                 <td>${holding.symbol || '-'}</td>
                                 <td>${holding.quantity || '-'}</td>
                                 <td>₹${holding.average_price || '-'}</td>
                                 <td>₹${holding.last_price || '-'}</td>
                                 <td class="${pnl >= 0 ? 'positive' : 'negative'}">₹${pnl}</td>
                             </tr>`;
                         });
                         
                         html += '</tbody></table></div>';
                     } else {
                         html = '<div class="no-data">No holdings data available or not authenticated</div>';
                     }
                     break;
                     
                 case 'fundamentals':
                     if (data.data) {
                         html = '<div class="fundamentals-grid">';
                         
                         // Financial ratios
                         html += '<div class="fundamentals-section"><h3>Financial Ratios</h3>';
                         html += `<div class="fundamentals-row"><span>P/E Ratio:</span> <span>${data.data.pe_ratio || '-'}</span></div>`;
                         html += `<div class="fundamentals-row"><span>EPS:</span> <span>₹${data.data.eps || '-'}</span></div>`;
                         html += `<div class="fundamentals-row"><span>Market Cap:</span> <span>₹${data.data.market_cap || '-'}</span></div>`;
                         html += `<div class="fundamentals-row"><span>Book Value:</span> <span>₹${data.data.book_value || '-'}</span></div>`;
                         html += `<div class="fundamentals-row"><span>Dividend Yield:</span> <span>${data.data.dividend_yield || '-'}%</span></div>`;
                         html += '</div>';
                         
                         // Additional info
                         html += '<div class="fundamentals-section"><h3>Additional Information</h3>';
                         html += `<div class="fundamentals-row"><span>52W High:</span> <span>₹${data.data.high_52_week || '-'}</span></div>`;
                         html += `<div class="fundamentals-row"><span>52W Low:</span> <span>₹${data.data.low_52_week || '-'}</span></div>`;
                         html += `<div class="fundamentals-row"><span>Face Value:</span> <span>₹${data.data.face_value || '-'}</span></div>`;
                         html += '</div>';
                         
                         html += '</div>'; // Close fundamentals-grid
                     } else {
                         html = '<div class="no-data">No fundamental data available</div>';
                     }
                     break;
                     
                 default:
                     html = '<div class="no-data">No data available</div>';
             }
             
             container.innerHTML = html;
         }
         
         // Initialize when DOM is ready
         document.addEventListener('DOMContentLoaded', function () {
             // Initialize chart with default symbol
             if (currentSymbol) {
                 showChart(currentSymbol);
             }
             
             // Timeframe selector
             document.querySelectorAll('.timeframe-btn').forEach(btn => {
                 btn.addEventListener('click', () => {
                     const interval = btn.dataset.interval;
                     updateChartInterval(interval);
                 });
             });
             
             // Watchlist item click
             document.querySelectorAll('#watchlist a').forEach(a => {
                 a.addEventListener('click', ev => {
                     ev.preventDefault();
                     const li = a.closest('li');
                     const symbol = li.dataset.symbol || a.textContent.trim();
                     showChart(symbol);
                     history.replaceState(null, '', '?symbol=' + encodeURIComponent(symbol));
                 });
             });
             
             // Buy/Sell buttons
             document.getElementById('btn-buy').onclick = () =>
                 alert('Buy order for ' + currentSymbol + ' (demo feature)');
             document.getElementById('btn-sell').onclick = () =>
                 alert('Sell order for ' + currentSymbol + ' (demo feature)');
             
             // Tab switching
             document.querySelectorAll('.nav-tab').forEach(tab => {
                 tab.addEventListener('click', () => {
                     const tabName = tab.dataset.tab;
                     switchTab(tabName);
                 });
             });
         });
         
         // Close suggestions on outside click
         document.addEventListener('click', e => {
             if (!document.getElementById('search-form').contains(e.target))
                 document.getElementById('suggestions').innerHTML = '';
         });
    </script>
</body>
</html>
