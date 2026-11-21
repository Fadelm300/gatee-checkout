<?php
session_start();

header('Cache-Control: no-store');
 header('Referrer-Policy: no-referrer');

$_SESSION['checkout_token'] ??= bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['checkout_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['checkout_token'], $token)) {
        http_response_code(403);
        exit('Invalid checkout session');}

    unset($_SESSION['payment_code']);

    header('Location: checkout.php', true, 303);
    exit;
    }
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
$resultUrl = '';

    if (!empty($paymentRecord['gateway_payment_id'])) {
        $resultUrl = 'result.php?' . http_build_query([
            'payment_id' => $paymentRecord['gateway_payment_id'],
            'payment_code' => $paymentCode,
        ]);
}

            $completed = in_array(
            $paymentRecord['status'] ?? '',
            ['paid', 'failed', 'cancelled', 'expired'],
            true );
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
    <?php if (!$completed): ?>
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
<?php endif; ?>
    <p id="checkout-message" role="status"><?= htmlspecialchars($checkoutMessage) ?></p>
    <p>
        <a
            id="result-link" href="<?= htmlspecialchars($resultUrl) ?>"
            <?= $resultUrl === '' ? 'hidden' : '' ?>
        >Check payment result</a>
    </p>
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
        <form method="post">
            <input
                type="hidden"
                name="checkout_token"value="<?= htmlspecialchars($_SESSION['checkout_token']) ?>"
            >
            <button type="submit">Start a new purchase</button>
        </form>
         <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
         <script src="assets/checkout.js"></script>
</body>
</html>