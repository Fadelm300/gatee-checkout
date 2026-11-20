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

echo json_encode([
    'message' => 'Payment attempt ready',
    'payment_id' => (string) $paymentRecord['id'],
    'status' => $paymentRecord['status'],
]);
