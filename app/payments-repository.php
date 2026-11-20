<?php

function findPaymentByCode(PDO $pdo, string $code): ?array
{
         $statement = $pdo->prepare(
            'SELECT p.*,
                c.name AS customer_name,
                c.email AS customer_email,
                c.mobile AS customer_mobile
         FROM payments p
              JOIN customers c ON c.id = p.customer_id
         WHERE p.payment_code = ?
          LIMIT 1'
    );

        $statement->execute([$code]);
        $payment = $statement->fetch();

     return $payment === false ? null : $payment;
}
function claimPaymentCreation(PDO $pdo, string $paymentId): bool
{
    $statement = $pdo->prepare(
        "UPDATE payments
         SET status = 'creating'
         WHERE id = ? AND status = 'initiated'"
          );

    $statement->execute([$paymentId]);

    return $statement->rowCount() === 1;
}


function saveGatewayPayment(
    PDO $pdo,
    string $paymentId,
      string $gatewayId,
    string $link
          ): bool { 
          $statement = $pdo->prepare(
               "UPDATE payments
               SET gateway_payment_id = ?,
                    payment_link = ?,
                    status = 'pending'
                         WHERE id = ? AND status = 'creating'"
                    );

       $statement->execute([$gatewayId, $link, $paymentId]);

    return $statement->rowCount() === 1;
}