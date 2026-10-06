<?php

declare(strict_types=1);

final class ReachService
{
    private string $apiToken;
    private string $profileUuid;
    private string $baseUrl;

    public function __construct()
    {
        $configPath = dirname(__DIR__, 2) . '/ecap_private/reach.php';

        if (!is_file($configPath)) {
            throw new RuntimeException('Reach configuration not found.');
        }

        $config = require $configPath;

        $this->apiToken   = $config['api_token'];
        $this->profileUuid = $config['profile_uuid'];
        $this->baseUrl    = rtrim($config['base_url'], '/');
    }

    public function getProfile(): array
{
    $profiles = $this->request('GET', '/profiles');

    foreach ($profiles as $resource) {
        foreach (($resource['profiles'] ?? []) as $profile) {
            if (($profile['uuid'] ?? null) === $this->profileUuid) {
                return $profile;
            }
        }
    }

    throw new RuntimeException('Configured Reach profile was not found.');
}

    private function request(
    string $method,
    string $endpoint,
    ?array $payload = null
): array {
    $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

    $ch = curl_init($url);

    if ($ch === false) {
        throw new RuntimeException('Could not initialize cURL.');
    }

    $headers = [
        'Authorization: Bearer ' . $this->apiToken,
        'Accept: application/json',
        'Content-Type: application/json',
    ];

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
    ];

    if ($payload !== null) {
        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            throw new RuntimeException('Could not encode Reach request payload.');
        }

        $options[CURLOPT_POSTFIELDS] = $json;
    }

    curl_setopt_array($ch, $options);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);

        throw new RuntimeException(
            'Reach connection error: ' . $error
        );
    }

    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    $data = json_decode($response, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        $message = is_array($data) && isset($data['message'])
            ? (string) $data['message']
            : 'Unknown Reach API error';

        throw new RuntimeException(
            "Reach API error ({$httpCode}): {$message}"
        );
    }

    if ($response === '' || $response === null) {
        return [];
    }

    if (!is_array($data)) {
        throw new RuntimeException('Invalid JSON response from Reach API.');
    }

    return $data;
    }
}