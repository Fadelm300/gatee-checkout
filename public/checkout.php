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
            <title>Checkout | gatee-checkout</title>
            <link rel="stylesheet" href="assets/app.css">
        </head>
        <body>
            <header class="topbar">
                <div class="topbar-inner">
                    <a class="brand" href="checkout.php" target="_top">
                        gatee-checkout
                    </a>

                    <nav class="app-nav" aria-label="Main navigation">
                        <a href="checkout.php" target="_top" aria-current="page">
                            Checkout
                        </a>
                        <a href="payments.php" target="_top">Payments</a>
                    </nav>
                </div>
            </header>

            <main class="page">
                <div class="page-heading">
                    <div>
                        <h1>Checkout</h1>
                        <p class="subtitle">
                            Your order and payment details in one place
                        </p>
                    </div>
                </div>

                <div class="checkout-layout">
                    <section class="panel">
                        <div class="panel-body">
                            <h2>Customer details</h2>
                            <p class="subtitle">
                                Enter your details to continue to payment
                            </p>

                            <?php if (!$completed): ?>
                                <form id="checkout-form" method="post">
                                    <input
                                        type="hidden"
                                        name="payment_code"
                                        value="<?= htmlspecialchars($paymentCode) ?>"
                                    >

                                    <div class="field">
                                        <label for="name">Name</label>
                                        <input
                                            id="name"
                                            name="name"
                                            type="text"
                                            autocomplete="name"
                                            maxlength="64"
                                            required
                                            value="<?= htmlspecialchars($paymentRecord['customer_name'] ?? '') ?>"
                                        >
                                    </div>

                                    <div class="field">
                                        <label for="email">Email</label>
                                        <input
                                            id="email"
                                            name="email"
                                            type="email"
                                            autocomplete="email"
                                            maxlength="64"
                                            required
                                            value="<?= htmlspecialchars($paymentRecord['customer_email'] ?? '') ?>"
                                        >
                                    </div>

                                    <div class="field">
                                        <label for="mobile">Mobile number</label>

                                        <div class="phone-field">
                                            <span class="dial-code">+973</span>
                                            <input
                                                id="mobile"
                                                name="mobile"
                                                type="tel"
                                                inputmode="numeric"
                                                autocomplete="tel-national"
                                                maxlength="8"
                                                pattern="[0-9]{8}"
                                                aria-label="Bahrain mobile number without country code"
                                                required
                                                value="<?= htmlspecialchars($paymentRecord['customer_mobile'] ?? '') ?>"
                                            >
                                        </div>
                                    </div>

                                    <button
                                        type="submit"
                                        class="button full-width"
                                        disabled
                                    >
                                        Pay now
                                    </button>
                                </form>
                            <?php else: ?>
                                <p>
                                    This checkout already has a recorded result.
                                    View the result or start a new purchase.
                                </p>
                            <?php endif; ?>

                            <noscript>
                                <p>Enable JavaScript to create your payment.</p>
                            </noscript>

                            <p
                                id="checkout-message"
                                class="checkout-message"
                                role="status"
                            >
                                <?= htmlspecialchars($checkoutMessage) ?>
                            </p>

                            <div class="purchase-actions">
                                <a
                                    id="result-link"
                                    class="button button--quiet"
                                    href="<?= htmlspecialchars($resultUrl) ?>"
                                    <?= $resultUrl === '' ? 'hidden' : '' ?>
                                >
                                    Check payment result
                                </a>

                                <form method="post">
                                    <input
                                        type="hidden"
                                        name="checkout_token"
                                        value="<?= htmlspecialchars($_SESSION['checkout_token']) ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="button button--quiet"
                                    >
                                        Start a new purchase
                                    </button>
                                </form>
                            </div>
                        </div>
                    </section>

                    <aside class="panel" aria-labelledby="order-title">
                        <div class="panel-heading">
                            <h2 id="order-title">Order summary</h2>
                            <span class="muted">1 item</span>
                        </div>

                        <div class="panel-body">
                            <h2><?= htmlspecialchars($product['name']) ?></h2>

                            <p class="price">
                                <?= htmlspecialchars($product['amount']) ?>
                                <span>
                                    <?= htmlspecialchars($product['currency']) ?>
                                </span>
                            </p>

                            <div class="summary-line">
                                <span class="muted">Amount due</span>
                                <strong>
                                    <?= htmlspecialchars($product['amount'] . ' ' . $product['currency']) ?>
                                </strong>
                            </div>

                            <p class="muted" style="margin:24px 0 0">
                                Card details are entered on the Gate-E payment page.
                            </p>
                        </div>
                    </aside>
                </div>

                <section
                    class="panel payment-area"
                    id="payment-area"
                    <?= $paymentLink === '' ? 'hidden' : '' ?>
                >
                    <div class="panel-heading">
                        <h2>Complete payment</h2>
                        <span class="muted">Gate-E</span>
                    </div>

                    <iframe
                        id="payment-frame"
                        class="payment-frame"
                        title="Gate-E payment"
                        width="100%"
                        height="700"
                        <?php if ($paymentLink !== ''): ?>
                            src="<?= htmlspecialchars($paymentLink) ?>"
                        <?php else: ?>
                            hidden
                        <?php endif; ?>
                    ></iframe>
                </section>
            </main>

            <script src="assets/vendor/jquery.min.js"></script>
            <script src="assets/checkout.js"></script>
        </body>
        </html>