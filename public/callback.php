<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../app/reconcile-payment.php';

    function callbackReply(int $httpStatus, array $body): never
    {
        http_response_code($httpStatus);
        echo json_encode($body, JSON_THROW_ON_ERROR);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        callbackReply(405, ['message' => 'POST requests only']);
    }

    $contentType = strtolower(trim(
        explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]
    ));

    if ($contentType !== 'application/json') {
        callbackReply(415, ['message' => 'JSON body required']);
    }

    try {
        [$id, $code] = paymentReference($_GET);

        $raw = file_get_contents('php://input', false, null, 0, 65537);

        if ($raw === false || strlen($raw) > 65536) {
            callbackReply(413, ['message' => 'Callback body is too large']);
        }

        $body = json_decode(
            $raw,
            true,
            512,
            JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING
        );

    if (!is_array($body) || array_is_list($body)) {
        callbackReply(400, ['message' => 'Invalid callback body']);
    }

    $eventPayment = $body['data']['payment'] ?? null;

    if (
        $eventPayment !== null &&
        (
            !is_array($eventPayment) ||
            ($eventPayment['id'] ?? null) !== $id ||
            ($eventPayment['code'] ?? null) !== $code
        )
    ) {
        callbackReply(400, ['message' => 'Callback payment does not match']);
    }

    $codeId = $body['code_id'] ?? null;

    if (
        $codeId !== null &&
        (
            (!is_string($codeId) && !is_int($codeId)) ||
            strlen((string) $codeId) > 128
        )
    ) {
        callbackReply(400, ['message' => 'Invalid callback acknowledgement']);
    }

    error_log(json_encode([
        'event' => 'callback_received',
        'code_id_present' => $codeId !== null,
        'has_payment' => $eventPayment !== null,
        'has_transaction' => isset($body['data']['transaction']),
    ], JSON_THROW_ON_ERROR));

    $pdo = require __DIR__ . '/../app/db.php';
    $payment = reconcilePayment($pdo, $id, $code);

    error_log(json_encode([
        'event' => 'callback_verified',
        'payment_id' => $id, 'status' => $payment['status'],
        'transaction_id' => $payment['transaction_id'],
        'code_id_present' => $codeId !== null,  ], JSON_THROW_ON_ERROR));

    callbackReply(
        200,  $codeId === null ? ['status' => 1] : ['code_id' => $codeId]
    );
        } catch (JsonException $exception) {
            callbackReply(400, ['message' => 'Invalid callback JSON']);
        } catch (InvalidArgumentException $exception) {
                callbackReply(404, ['message' => 'Payment not found']);
        } 
        catch (Throwable $exception) {
            error_log('callback_verification_unavailable');

            callbackReply(503, [
                'message' => 'Payment confirmation is temporarily unavailable',
            ]);
        }