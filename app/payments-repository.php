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


function savePaymentResult(PDO $pdo, array $expected, array $verified): void
{
    $pdo->beginTransaction();

    try {
        $statement = $pdo->prepare(
          'SELECT * FROM payments WHERE id = ? FOR UPDATE'
        );
        $statement->execute([$expected['id']]);
        $current = $statement->fetch();

        if (!$current ||
            $current['payment_code'] !== $expected['payment_code'] ||
            $current['gateway_payment_id'] !== $expected['gateway_payment_id'] ||
            $current['amount'] !== $expected['amount'] ||
            $current['currency'] !== $expected['currency']
        ) {
            throw new RuntimeException('Payment changed during verification.');
        }

        // Do not overwrite an already confirmed payment
        $keepCurrent = $current['status'] === 'paid';

        if ($verified['status'] !== 'paid' &&
            $current['transaction_date'] !== null
        ) {
            $newDate = $verified['transaction_date'] ?? '';
            $oldDate = $current['transaction_date'];

            $newId = $verified['transaction_id'] ?? '';
            $oldId = $current['transaction_id'] ?? '';

            $olderId = strlen($newId) < strlen($oldId) ||
                (
                  strlen($newId) === strlen($oldId) &&
                  strcmp($newId, $oldId) < 0
                );

            $keepCurrent = $keepCurrent ||
                $newDate < $oldDate ||
                ($newDate === $oldDate && $olderId);
        }

        if (
          $verified['status'] === 'pending' &&
          $verified['transaction_id'] === null &&
          in_array($current['status'], ['failed', 'cancelled', 'expired'], true)
        ) {
            $keepCurrent = true;
        }

        $fields = [
            'status', 'transaction_id',
            'transaction_status',
            'transaction_date', 'response_code',
            'response_message'
        ];

        $changed = false;

        foreach ($fields as $field) {
           $changed = $changed || $current[$field] !== $verified[$field];
        }

        if (!$keepCurrent && $changed) {

            $statement = $pdo->prepare(
                'UPDATE payments
                 SET status = ?, transaction_id = ?, transaction_status = ?,
                     transaction_date = ?, response_code = ?, response_message = ?
                 WHERE id = ?'
            );

            $values = array_map(
               static fn ($field) => $verified[$field],
               $fields
            );

            $statement->execute([...$values, $current['id']]);
        }

        $pdo->commit();

    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}


    function listPayments(PDO $pdo, int $page = 1, int $limit = 20): array
       {
          $statement = $pdo->prepare(
            'SELECT p.id, p.payment_code, p.amount, p.currency,
                    p.status, p.transaction_status, p.created_at,
                 c.name AS customer_name
            FROM payments p
          JOIN customers c ON c.id = p.customer_id
             ORDER BY p.id DESC
            LIMIT ? OFFSET ?' );

        $statement->bindValue(1, $limit, PDO::PARAM_INT);
        $statement->bindValue(2, ($page - 1) * $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
        }

    function countPayments(PDO $pdo): int{
    return (int) $pdo->query('SELECT COUNT(*) FROM payments')  ->fetchColumn(); }

    function findPaymentById(PDO $pdo, string $id): ?array
    {
        $statement = $pdo->prepare(
            'SELECT p.*, c.name AS customer_name,
                    c.email AS customer_email,
                    c.mobile AS customer_mobile
            FROM payments p
            JOIN customers c ON c.id = p.customer_id
            WHERE p.id = ?
            LIMIT 1' ) ;

        $statement->execute([$id]);
        $payment = $statement->fetch();

        return $payment === false ? null : $payment ;
    }
