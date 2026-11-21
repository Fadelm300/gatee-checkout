<?php

    require_once __DIR__ . '/../../app/review-access.php';
    requireReviewAccess(true);

    header('Content-Type: application/json; charset=utf-8');

    function detailsReply(int $status, array $body): never
    {  http_response_code($status);
        echo json_encode($body, JSON_THROW_ON_ERROR);
        exit; }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Allow: GET');
        detailsReply(405, ['message' => 'GET requests only']);}

    $id = $_GET['id'] ?? '';

    if (
        !is_string($id) ||
        !preg_match('/^[1-9][0-9]{0,19}$/D', $id) ||
        (strlen($id) === 20 && strcmp($id, '18446744073709551615') > 0)
    ) {
        detailsReply(400, ['message' => 'Invalid payment ID']);
    }

    require_once __DIR__ . '/../../app/payments-repository.php';
    require_once __DIR__ . '/../../app/gatee.php';
    require_once __DIR__ . '/../../app/payment-verification.php';

    try {
        $pdo = require __DIR__ . '/../../app/db.php';
        $payment = findPaymentById($pdo, $id);
    } catch (PDOException $exception) {
        detailsReply(503, [ 'message' => 'Payment records are temporarily unavailable',]);
    }

    if ($payment === null) {  detailsReply(404, ['message' => 'Payment not found']);}

      $fields = ['id', 'payment_code', 'gateway_payment_id',
                'customer_name',  'customer_email', 'customer_mobile' ,
                'amount', 'currency' , 'status',
                'transaction_id', 'transaction_status', 'transaction_date' ,
                'response_code' , 'response_message', 'created_at', 'updated_at' ,
                ];

    $local = array_intersect_key($payment, array_flip($fields));

      $gateway = [
         'available' => false ,
        'message' => 'No gateway payment has been created for this record.' ,
         'fetched_at' => null,
        'payment' => null ,
        'transaction' => null,
        ];

    if (!empty($payment['gateway_payment_id'])) {
        try {
            $response = getGatewayPayment($payment['gateway_payment_id']);
            $verified = verifyGatewayPayment($payment, $response);
            $remote = $response['data']['payment'];

            $gateway = [
                'available' => true,
                'message' => '',
                'fetched_at' => gmdate('Y-m-d\TH:i:s\Z'),
                'payment' => [
                    'id' => (string) $remote['id'] ,
                    'code' => $remote['code'], 
                     'status' => $remote['status'],
                    'amount' => paymentAmount($remote['amount']) ,
                    'currency' => $remote['currency'],
                ],
                'transaction' => array_diff_key(  $verified, ['status' => true] ),
            ];
        } catch (Throwable $exception) {
            $gateway['message'] ='Gateway details unavailable. Database details are still shown.';
            error_log('payment_details_gateway_unavailable');
        }
    }
    detailsReply(200, [ 'local' => $local,'gateway' => $gateway, ]);