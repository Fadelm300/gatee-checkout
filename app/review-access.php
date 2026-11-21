<?php

    function requireReviewAccess(bool $json = false): void
    {
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');
        header('X-Content-Type-Options: nosniff');

        if (getenv('REVIEW_AUTH_REQUIRED') === '0') { return;}

        $user = getenv('REVIEW_USER') ?: '';
        $password = getenv('REVIEW_PASSWORD') ?: '';
        $configured = $user !== '' && $password !== '';

        if (
            $configured &&
            hash_equals($user, $_SERVER['PHP_AUTH_USER'] ?? '') &&
            hash_equals($password, $_SERVER['PHP_AUTH_PW'] ?? '')
            ) { return; }

        http_response_code($configured ? 401 : 503) ;

        if ($configured) {
            header('WWW-Authenticate: Basic realm="Payment review"') ; }
        $message = $configured
            ? 'Payment review access required.'
            : 'Payment review is not configured.';

        header($json
            ? 'Content-Type: application/json; charset=utf-8'
            : 'Content-Type: text/plain; charset=utf-8' );

        echo $json ? json_encode(['message' => $message]) : $message;
        exit;
    }