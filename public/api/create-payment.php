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

echo json_encode(['message' => 'Validation passed. Payment is not created yet']);