<?php

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

require_once __DIR__ . '/../app/reconcile-payment.php';

    $config = require __DIR__ . '/../app/config.php';
    $payment = null;
    $notice = '';
    $refreshUrl = '';

    try {
        [$id, $code] = paymentReference($_GET);
        $pdo = require __DIR__ . '/../app/db.php';

        $payment = findPaymentByCode($pdo, $code);

        if ($payment === null || $payment['gateway_payment_id'] !== $id) {
            throw new InvalidArgumentException('Payment not found.');
        }

        $refreshUrl = 'result.php?' . http_build_query([
            'payment_id' => $id,
            'payment_code' => $code,
        ]);

        $payment = reconcilePayment($pdo, $id, $code);
    } catch (InvalidArgumentException $exception) {
        $payment = null;
        http_response_code(404);
        $notice = 'Payment not found.';
    } catch (Throwable $exception) {
        http_response_code(503);
        $notice = 'Unable to confirm the latest result. Please check again shortly.';
        error_log('result_verification_unavailable');
    }

$title = 'Payment result';

        if ($payment !== null) {
            $title = match ($payment['status']) {
                'paid' => 'Payment successful',
                'failed' => 'Payment declined',
                'cancelled' => 'Payment cancelled',
                'expired' => 'Payment expired',
                default => in_array(
                    $payment['transaction_status'],
                    ['failed', 'declined', 'cancelled'],
                    true
                ) ? 'Payment declined' : 'Awaiting payment confirmation',
            };
        }

    function resultText(mixed $value): string
    {return htmlspecialchars(
    (string) ($value ?? 'Not available'),
            ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');}
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= resultText($title) ?></title>
</head>
<body>


<h1><?= resultText($title) ?></h1>
<?php if ($notice !== ''): ?>
    <p role="alert"><?= resultText($notice)  ?></p>
<?php endif; ?>



  <?php if ($payment !== null): ?>
 <p>The details below are the last result verified with Gate-E.</p>

    <table>
        <tbody>
        <?php foreach ([
            'Payment reference' => 'payment_code',
            'Gateway payment ID' => 'gateway_payment_id',
            'Amount' => 'amount',
            'Currency' => 'currency',
            'Payment status' => 'status',
            'Transaction ID' => 'transaction_id',
            'Transaction status' => 'transaction_status',
            'Transaction time' => 'transaction_date',
            'Gateway response code' => 'response_code',
            'Gateway response' => 'response_message',
        ] as $label => $field): ?>
                 <tr>
                <th scope="row"><?= resultText($label) ?></th>
                <td data-field="<?= resultText($field) ?>"><?= resultText($payment[$field]) ?></td>
                 </tr>
        <?php endforeach; ?>
 </tbody>
     </table>
  <?php endif; ?>

<?php if ($refreshUrl !== ''): ?>
    <p><a href="<?= resultText($refreshUrl) ?>">Check again</a></p>
<?php endif; ?>

<p>
    <a href="<?= resultText($config['app']['checkout_url']) ?>" target="_top">  Return to checkou </a></p>
</body>
</html>