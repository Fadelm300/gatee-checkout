<?php
require_once __DIR__ . '/../app/review-access.php';
requireReviewAccess();
require_once __DIR__ . '/../app/payments-repository.php';

$page = $_GET['page'] ?? '1';
if (!is_string($page) || !preg_match('/^[1-9][0-9]{0,5}$/D', $page)) {
    http_response_code(400);
    exit('Invalid page number.');
}
$page = (int) $page;
$payments = [];
$total = 0;
$notice = '';
try {
    $pdo = require __DIR__ . '/../app/db.php';
    $total = countPayments($pdo);
    $payments = listPayments($pdo, $page);
} catch (PDOException $exception) {
    http_response_code(503);
    $notice = 'Payment records are temporarily unavailable.';
}
function paymentText(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function paymentState(?string $status): string
{
    return match ($status) {
        'paid' => 'paid',
        'failed', 'declined', 'create_error' => 'failed',
        'cancelled', 'canceled', 'expired' => 'closed',
        default => 'pending',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Payments | gatee-checkout</title>
        <link rel="stylesheet" href="assets/vendor/bootstrap.min.css" />
        <link rel="stylesheet" href="assets/app.css" />
    </head>
    <body>
        <header class="topbar">
            <div class="topbar-inner">
                <a class="brand" href="checkout.php" target="_top">gatee-checkout</a>
                <nav class="app-nav" aria-label="Main navigation">
                    <a href="checkout.php" target="_top">Checkout</a>
                    <a href="payments.php" target="_top" aria-current="page">Payments</a>
                </nav>
            </div>
        </header>
        <main class="page">
            <div class="page-heading">
                <div>
                    <h1>Payments</h1>
                    <p class="subtitle">Review stored payments and their latest gateway details</p>
                </div>
                <a class="button" href="checkout.php">Open checkout</a>
            </div>
            <section class="panel" aria-labelledby="activity-title">
                <div class="panel-heading">
                    <h2 id="activity-title">Payment activity</h2>
                    <?php if ($notice === '' ): ?>
                    <span class="muted"><?= $total ?> records · Latest first</span>
                    <?php endif; ?>
                </div>
                <?php if ($notice !== ''): ?>
                <p class="empty-state notice" role="alert"><?= paymentText($notice) ?></p>
                <?php elseif ($payments === []): ?>
                <p class="empty-state">No payments found.</p>
                <?php else: ?>
                <div class="table-scroll" tabindex="0" aria-label="Payment records">
                    <table class="payment-table">
                        <thead>
                            <tr>
                                <th scope="col">Reference</th>
                                <th scope="col">Customer</th>
                                <th scope="col">Amount</th>
                                <th scope="col">Payment</th>
                                <th scope="col">Transaction</th>
                                <th scope="col">Created · UTC</th>
                                <th scope="col">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                            <tr data-payment-id="<?= paymentText($payment['id']) ?>">
                                <td class="reference"><?= paymentText($payment['payment_code']) ?></td>
                                <td><?= paymentText($payment['customer_name']) ?></td>
                                <td class="amount">
                                    <?= paymentText($payment['amount']) ?>
                                    <span class="muted"><?= paymentText($payment['currency']) ?></span>
                                </td>
                                <td>
                                    <span class="status-label status-label--<?= paymentState($payment['status']) ?>">
                                        <?= paymentText($payment['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($payment['transaction_status'] === null ): ?>
                                    <span class="muted">Not available</span>
                                    <?php else: ?>
                                    <span class="status-label status-label--<?= paymentState($payment['transaction_status']) ?>">
                                        <?= paymentText($payment['transaction_status']) ?>
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td class="date"><?= paymentText($payment['created_at']) ?></td>
                                <td>
                                    <button
                                        type="button"
                                        class="button button--quiet button--small"
                                        data-view-payment="<?= paymentText($payment['id']) ?>"
                                    >
                                        View
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                <nav class="pagination-bar" aria-label="Payment pages">
                    <span class="muted">Page <?= $page ?></span>
                    <div class="pagination-actions">
                        <?php if ($page > 1): ?>
                        <a class="button button--quiet button--small" href="?page=<?= $page - 1 ?>">
                            Previous
                        </a>
                        <?php endif; ?> <?php if ($page * 20 < $total ): ?>
                        <a class="button button--quiet button--small" href="?page=<?= $page + 1 ?>">
                            Next
                        </a>
                        <?php endif; ?>
                    </div>
                </nav>
            </section>
        </main>
        <div
            class="modal"
            id="payment-modal"
            tabindex="-1"
            aria-labelledby="payment-modal-title"
            aria-hidden="true"
        >
            <div class="modal-dialog modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title" id="payment-modal-title">Payment details</h2>
                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Close"
                        ></button>
                    </div>
                    <div class="modal-body">
                        <p id="details-message" class="details-message" role="status"></p>
                        <div class="detail-columns">
                            <section class="detail-section" aria-labelledby="local-title">
                                <h3 id="local-title">Database</h3>
                                <dl id="local-details" class="details-list"></dl>
                            </section>
                            <section class="detail-section" aria-labelledby="gateway-title">
                                <h3 id="gateway-title">Gate-E</h3>
                                <p id="gateway-message" class="details-message" role="status"></p>
                                <dl id="gateway-details" class="details-list"></dl>
                            </section>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="button button--quiet" data-bs-dismiss="modal">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <script src="assets/vendor/jquery.min.js"></script>
        <script src="assets/vendor/bootstrap.bundle.min.js"></script>
        <script src="assets/payments.js"></script>
    </body>
</html>
