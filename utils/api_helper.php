<?php

function initJsonApi() {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 0);

    if (ob_get_level() === 0) {
        ob_start();
    }

    header('Content-Type: application/json; charset=utf-8');
}

function clearApiOutputBuffer() {
    if (ob_get_length()) {
        ob_clean();
    }
}

function apiJsonResponse(array $payload, int $statusCode = 200) {
    clearApiOutputBuffer();
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function apiSuccess(array $payload = [], int $statusCode = 200) {
    apiJsonResponse(array_merge(['status' => 'success'], $payload), $statusCode);
}

function apiError(string $message, int $statusCode = 400, array $extra = []) {
    apiJsonResponse(array_merge([
        'status' => 'error',
        'message' => $message
    ], $extra), $statusCode);
}

function requireJsonRequestBody() {
    $rawInput = file_get_contents('php://input');

    if ($rawInput === false || trim($rawInput) === '') {
        throw new Exception('Empty request body');
    }

    $input = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }

    if (!is_array($input)) {
        throw new Exception('Invalid JSON payload');
    }

    return $input;
}

function apiCloseConnection($conn) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>
