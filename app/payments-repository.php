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