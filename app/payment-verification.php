<?php

function paymentAmount(mixed $value): string
{
    if (!is_string($value) && !is_int($value) && !is_float($value)) {
        throw new UnexpectedValueException('Missing payment amount.'); }

    $value = (string) $value;

    if (!preg_match('/^(0|[1-9][0-9]{0,9})(?:\.([0-9]{1,3})0*)?$/D', $value, $parts)) {
        throw new UnexpectedValueException('Invalid payment amount.');}
    return $parts[1] . '.' . str_pad($parts[2] ?? '', 3, '0');
}

function paymentIdentifier(mixed $value): string
{
    if (
        (!is_string($value) && !is_int($value)) ||
        !preg_match('/^[0-9]{1,64}$/D', (string) $value) ) {
        throw new UnexpectedValueException('Invalid payment identifier.');
    } return (string) $value;
}


function verifyGatewayPayment(array $local, array $response): array
{
    if (!in_array($response['status'] ?? null, [1, '1'], true)) {
        throw new UnexpectedValueException('Payment lookup was not successful.');
    }

    $remote = $response['data']['payment'] ?? null;

    if (
        !is_array($remote) ||
        paymentIdentifier($remote['id'] ?? null) !== $local['gateway_payment_id'] ||
        ($remote['code'] ?? null) !== $local['payment_code'] ||
        paymentAmount($remote['amount'] ?? null) !== paymentAmount($local['amount']) ||
        ($remote['currency'] ?? null) !== $local['currency']
    ) {
        throw new UnexpectedValueException('Payment details do not match.');
    }

    $statuses = [
        'unpaid' => 'pending',
        'pending' => 'pending',
        'paid' => 'paid',
        'failed' => 'failed',
        'cancelled' => 'cancelled',
        'canceled' => 'cancelled',
        'expired' => 'expired',
    ];

    if (
        !is_string($remote['status'] ?? null) ||
        !isset($statuses[$remote['status']])
    ) {
        throw new UnexpectedValueException('Unknown payment status.');
    }

    $status = $statuses[$remote['status']];
    $transactions = $response['data']['transaction'] ?? [];

    if (!is_array($transactions) || !array_is_list($transactions)) {
        throw new UnexpectedValueException('Invalid transaction list.');}

    $candidates = [];
    foreach ($transactions as $transaction) {
        if (!is_array($transaction)) {
            throw new UnexpectedValueException('Invalid transaction.');
        }

        if (($transaction['type'] ?? 'pay') !== 'pay') {
            continue;}


        $id = paymentIdentifier($transaction['id'] ?? null);
        $transactionStatus = $transaction['status'] ?? null;




        if (
            !is_string($transactionStatus) ||
            !in_array(
                $transactionStatus,
                ['paid', 'failed', 'declined', 'cancelled', 'pending'],
                true
            ) ||
            paymentAmount($transaction['amount'] ?? null) !== paymentAmount($local['amount']) ||
            ($transaction['currency'] ?? null) !== $local['currency']
        ) {
            throw new UnexpectedValueException('Transaction details do not match.');
        }

        if (
            isset($transaction['payment_id']) &&
            paymentIdentifier($transaction['payment_id']) !== $local['gateway_payment_id']
        ) {
            throw new UnexpectedValueException('Transaction belongs to another payment.');
        }


        $date = $transaction['payment_date'] ?? null;
        $parsed = is_string($date)
            ? DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $date)
            : false;

        if (!$parsed || $parsed->format('Y-m-d H:i:s') !== $date) {
            throw new UnexpectedValueException('Invalid transaction date.');
        }

        if (
            $transactionStatus === 'paid' &&
            (
                paymentAmount($transaction['paid_amount'] ?? null) !== paymentAmount($local['amount']) ||
                ($transaction['paid_currency'] ?? null) !== $local['currency']
            )
        ) {
            throw new UnexpectedValueException('Captured amount does not match.');
        }

        foreach (['response_code' => 64, 'response_message' => 512] as $field => $limit) {
            if (
                isset($transaction[$field]) &&
                (
                    !is_string($transaction[$field]) ||
                    mb_strlen($transaction[$field]) > $limit
                )
            ) {
                throw new UnexpectedValueException('Invalid transaction response.');
            }
        }

        $candidates[] = [
            'transaction_id' => $id,
            'transaction_status' => $transactionStatus,
            'transaction_date' => $date,
            'response_code' => $transaction['response_code'] ?? null,
            'response_message' => $transaction['response_message'] ?? null,
        ];
    }

    // Choose by gateway date and identifier, not by array position.
    usort($candidates, static function (array $a, array $b): int {
        return strcmp($b['transaction_date'], $a['transaction_date'])
            ?: strlen($b['transaction_id']) <=> strlen($a['transaction_id'])
            ?: strcmp($b['transaction_id'], $a['transaction_id']); });

    $selected = $candidates[0] ?? null;

    if ($status === 'paid') {
        $paid = array_values(array_filter(
            $candidates,
            static fn ($transaction) => $transaction['transaction_status'] === 'paid'  ));

        $selected = $paid[0] ?? null;

        if ($selected === null) {
            throw new UnexpectedValueException('Paid payment has no verified transaction.');
        }
    } elseif (array_filter(
        $candidates,
        static fn ($transaction) => $transaction['transaction_status'] === 'paid'
    )) {
        throw new UnexpectedValueException('Payment and transaction statuses disagree.');
    }

    return ['status' => $status] + ($selected ?? [
        'transaction_id' => null,'transaction_status' => null, 'transaction_date' => null,  'response_code' => null, 'response_message' => null,
    ]);
}