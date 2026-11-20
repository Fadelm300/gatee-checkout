<?php

function gateeRequest(string $action, array $payment): array
{
    $config = require __DIR__ . '/config.php';
    $gatee = $config['gatee'];

    $body = [
        'api' => [
            'version' => '01',
            'user' => $gatee['user'],
            'key' => $gatee['key'],
            'resource' => 'payment',
            'action' => $action,
        ],
        'payment' => $payment,
    ];

    $curl = curl_init($gatee['url']);

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($body, JSON_THROW_ON_ERROR),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($curl);
    $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    if ($response === false) {
        throw new RuntimeException('Unable to reach Gate-E.');
    }

    if ($httpStatus < 200 || $httpStatus >= 300) {
        throw new RuntimeException('Unexpected HTTP response from Gate-E.');
    }

    $data = json_decode(
        $response,
        true,
        512,
        JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING
    );

    if (!is_array($data)) {
        throw new RuntimeException('Invalid response from Gate-E.');
    }

    return $data;
}