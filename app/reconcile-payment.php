<?php

require_once __DIR__ . '/gatee.php';
require_once __DIR__ . '/payments-repository.php' ;
require_once __DIR__ . '/payment-verification.php';

function paymentReference(array $input): array
{
    $id = $input['payment_id'] ?? '';
     $code = $input['payment_code'] ?? '';

    if ( !is_string($id) ||
        !preg_match('/^[0-9]{1,64}$/D', $id) ||
        !is_string($code) ||
        !preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $code)
    ) {
        throw new InvalidArgumentException('Payment not found.' ); }
     return [$id, $code];
}

function reconcilePayment(PDO $pdo, string $id, string $code): array
{
    $local = findPaymentByCode($pdo, $code);

    if ($local === null || $local['gateway_payment_id'] !== $id) {
        throw new InvalidArgumentException('Payment not found.') ;
                   }

    // The return URL identifies the payment; Gate-E verifies its status.
    $response = getGatewayPayment($id);
     $verified = verifyGatewayPayment($local, $response);
     savePaymentResult($pdo, $local, $verified) ;

    return findPaymentByCode($pdo, $code) ;
}