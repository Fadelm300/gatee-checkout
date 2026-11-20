<?php
session_start();

if (empty($_SESSION['payment_code'])) {
    $_SESSION['payment_code'] = 'FC-' . bin2hex(random_bytes(16));
}

$paymentCode = $_SESSION['payment_code'];
$config = require __DIR__ . '/../app/config.php';
$product = $config['product'];
require_once __DIR__ . '/../app/payments-repository.php';

$paymentRecord = null ;
$paymentLink = '' ;
$checkoutMessage = '';

try{
      $pdo = require __DIR__ . '/../app/db.php';
     $paymentRecord = findPaymentByCode($pdo, $paymentCode);
  
    if (
        $paymentRecord !== null &&
        $paymentRecord['status'] === 'pending'  &&
        !empty($paymentRecord['gateway_payment_id']) &&
            !empty($paymentRecord['payment_link'])
    ) {
            $paymentLink = $paymentRecord['payment_link'];
        $checkoutMessage = 'Payment ready';
        }
} catch (PDOException $exception) {    $checkoutMessage = 'Unable to restore your checkout. Please try again later.';
}
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
    <input id="name" name="name" type="text" maxlength="64" required 
    value="<?= htmlspecialchars($paymentRecord['customer_name'] ?? '') ?>">
   
<label for="email">Email</label>
    <input id="email" name="email" type="email" maxlength="64" required
    value="<?= htmlspecialchars($paymentRecord['customer_email'] ?? '') ?>">

    <label for="mobile">Mobile (+973)</label>
    <input id="mobile" name="mobile" type="tel" maxlength="32" required
    value="<?= htmlspecialchars($paymentRecord['customer_mobile'] ?? '') ?>">
    <!-- AJAX later -->
                <button type="submit" disabled>Pay now</button>
    </form>

    <p id="checkout-message" role="status"><?= htmlspecialchars($checkoutMessage) ?></p>

    <iframe
       id="payment-frame"
      title="Gate-E payment"
          width="100%"
         height="700"
         <?php if ($paymentLink !== ''): ?>
            src="<?= htmlspecialchars($paymentLink) ?>"
      <?php else: ?>
         hidden
      <?php endif; ?>
            ></iframe>

         <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
         <script src="assets/checkout.js"></script>
</body>
</html>