<?php
session_start();

if (empty($_SESSION['payment_code'])) {
    $_SESSION['payment_code'] = 'FC-' . bin2hex(random_bytes(16));
}

$paymentCode = $_SESSION['payment_code'];
$config = require __DIR__ . '/../app/config.php';
$product = $config['product'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checkout</title>
</head>



<body>

    <h1><?= htmlspecialchars($product['name']) ?></h1>
    <p><?= htmlspecialchars($product['amount'] . ' ' . $product['currency']) ?></p>
    <form id="checkout-form" method="post">
<input type="hidden" name="payment_code" value="<?= htmlspecialchars($paymentCode) ?>">     <label for="name">Name</label>
    <input id="name" name="name" type="text" maxlength="64" required>
   
<label for="email">Email</label>
    <input id="email" name="email" type="email" maxlength="64" required>

    <label for="mobile">Mobile (+973)</label>
    <input id="mobile" name="mobile" type="tel" maxlength="32" required>
    <!-- AJAX later -->
                <button type="submit" disabled>Pay now</button>
    </form>

    <p id="checkout-message" role="status"></p>

    <iframe
        id="payment-frame"
        title="Gate-E payment"
        width="100%"
        height="700"
        hidden
    ></iframe>

         <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
         <script src="assets/checkout.js"></script>
</body>
</html>