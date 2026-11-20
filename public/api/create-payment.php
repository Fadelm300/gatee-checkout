<?php

session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');

    echo json_encode(['message' => 'POST requests only']);
    exit;
}

$input = [];

foreach (['name', 'email', 'mobile', 'payment_code'] as $field) {
    $input[$field] = is_string($_POST[$field] ?? null)
        ? trim($_POST[$field])
             : '';
        }

        if (
            empty($_SESSION['payment_code']) ||
            !hash_equals($_SESSION['payment_code'], $input['payment_code'])
        ) 
        { http_response_code(403);
            echo json_encode(['message' => 'Invalid checkout session']);
            exit;
            }

        $errors = [];

    if ($input['name'] === '' || mb_strlen($input['name']) > 64) {
           $errors['name'] = 'Enter a name of up to 64 characters';
}

if (
    strlen($input['email']) > 64 ||
    !filter_var($input['email'], FILTER_VALIDATE_EMAIL)
    ) {
       $errors['email'] = 'Enter a valid email addres';
    }

if (!preg_match('/^[0-9]{8}$/', $input['mobile'])) {
    $errors['mobile'] = 'Enter an 8-digit Bahrain mobile number';
}

         if ($errors) {
          http_response_code(422);
          echo json_encode(['errors' => $errors]);
           exit;
             }

$config = require __DIR__ . '/../../app/config.php';
$product = $config['product'];
$pdo = null;
  require_once __DIR__ . '/../../app/payments-repository.php';
    require_once __DIR__ . '/../../app/gatee.php';
try {
    $pdo = require __DIR__ . '/../../app/db.php';
   
        $existingPayment = findPaymentByCode($pdo, $input['payment_code']);

        if ($existingPayment === null) {
           $pdo->beginTransaction();  
    $customer = $pdo->prepare('INSERT INTO customers (name, email, mobile) VALUES (?, ?, ?)');
    $customer->execute([$input['name'], $input['email'], $input['mobile']]);

    $payment = $pdo->prepare(
        'INSERT INTO payments (customer_id, payment_code, amount, currency) VALUES (?, ?, ?, ?)'
    );
    $payment->execute([
        $pdo->lastInsertId(),
        $input['payment_code'],
        $product['amount'],
        $product['currency'],
    ]);

    $pdo->commit();
    }

    $paymentRecord = findPaymentByCode($pdo, $input['payment_code']);
} catch (PDOException $exception) {
    if ($pdo !== null && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
        http_response_code(409);
        echo json_encode(['message' => 'A payment attempt already exists for this checkout']);
        exit;
    }

    http_response_code(500);
    echo json_encode(['message' => 'Unable to save the payment attempt']);
    exit;
}

if (
    !empty($paymentRecord['gateway_payment_id']) &&
    !empty($paymentRecord['payment_link'])
) {
    echo json_encode([
        'message' => 'Payment ready',
        'payment_link' => $paymentRecord['payment_link'],
    ]);
    exit;
}

if ($paymentRecord['status'] !== 'initiated') {
    http_response_code(409);
    echo json_encode([
        'message' => 'This payment attempt needs checking before continuing.',
    ]);
    exit;
}

session_write_close();

try {
    if (!claimPaymentCreation($pdo, (string) $paymentRecord['id'])) {
        http_response_code(409);
        echo json_encode(['message' => 'Payment creation already started.']);
        exit;
    }

    $response = gateeRequest('create', [
        'type' => 'pay',
        'code' => $paymentRecord['payment_code'],
        'amount' => (float) $paymentRecord['amount'],
        'currency' => $paymentRecord['currency'],
        'gateway' => [
            'id' => $config['gatee']['gateway_id'],
        ],
        'client' => [
            'name' => $paymentRecord['customer_name'],
            'email' => $paymentRecord['customer_email'],
            'mobile_code' => '973',
            'mobile' => $paymentRecord['customer_mobile'],
        ],
        'notification' => [
            'email_send' => 0,
            'whatsapp_send' => 0,
            'sms_send' => 0,
        ],
        'settings' => [
            'create_code' => 0,
            'calculate_payment' => 0,
        ],
    ]);

    if (!in_array($response['status'] ?? null, [0, 1, '0', '1'], true)) {
        throw new RuntimeException('Missing gateway status.');
    }

    if ((int) $response['status'] === 0) {
        // A duplicate code may belong to an earlier unconfirmed creation.
        if (in_array($response['status_code'] ?? '', ['GATEE_300', 'GATEE_306'], true)) {
            throw new RuntimeException('Payment reference needs checking.');
        }

        $statement = $pdo->prepare(
            "UPDATE payments SET status = 'create_error'
             WHERE id = ? AND status = 'creating'"
        );
        $statement->execute([$paymentRecord['id']]);

        http_response_code(422);
        echo json_encode(['message' => 'Gate-E could not create this payment.']);
        exit;
    }

    $gatewayPayment = $response['data']['payment'] ?? [];
    $gatewayId = (string) ($gatewayPayment['id'] ?? '');
    $link = $gatewayPayment['link'] ?? '';

    if (
        !ctype_digit($gatewayId) ||
        strlen($gatewayId) > 64 ||
        !is_string($link) ||
        !filter_var($link, FILTER_VALIDATE_URL) ||
        parse_url($link, PHP_URL_SCHEME) !== 'https' ||
        !preg_match('/(^|\.)gate-e\.(net|com|co)$/i', parse_url($link, PHP_URL_HOST) ?? '')
    ) {
        throw new RuntimeException('Invalid gateway payment details.');
    }

    if (!saveGatewayPayment($pdo, (string) $paymentRecord['id'], $gatewayId, $link)) {
        throw new RuntimeException('Unable to save gateway details.');
    }

    echo json_encode([
        'message' => 'Payment ready',
        'payment_link' => $link,
    ]);
        } catch (Throwable $exception) {
    // keep the attempt unchanged when the gateway outcome is uncertain
            http_response_code(502);
         echo json_encode([
        'message' => 'Unable to confirm your payment. Please check again later.',
      ]);
}
