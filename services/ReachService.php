<?php

declare(strict_types=1);

final class ReachService
{
    private const FIELD_AMBITO = '184de0f0-85ea-41f5-ae43-67e7f159a64a';
    private const FIELD_UNIVERSIDAD = '18d299f4-5ba3-4be2-82aa-5fea77d10e01';
    private const FIELD_TIPO_UNIVERSIDAD = 'fc552b4b-3093-4abe-ba06-f37b81a76b6a';

    private const AMBITO_OPTIONS = [
        1 => '67a3df12-9f57-4ed6-9208-fa77f91b91b1', // Sector público nacional
        2 => 'b56ea5e7-2f7f-4961-b196-c9ef02cd04f5', // Sector público provincial
        3 => '926ed5fa-4af3-4de6-af14-2a9adba969e8', // Sector público municipal
        4 => '42344021-e944-41a3-9f44-fba71fb5a511', // Poder Judicial / Ministerio Público
        5 => '71f42f65-d6e2-40b0-893a-2475380de226', // Sector privado
        6 => '17ea4a8c-79d7-4a1c-8bd5-760525c6da3d', // Ejercicio independiente
        7 => '9c69349a-b33c-40a1-967a-638d011c08b9', // Docencia / ámbito académico
        8 => 'e6277851-27a2-4f58-afa8-548311f2e523', // Otro
    ];

    private const TIPO_UNIVERSIDAD_OPTIONS = [
        'publica' => '5cb6d016-9c3b-411f-a530-b8029cadf07e',
        'privada' => '1d5c7098-c1f8-4e66-8156-8d4c99aaee7c',
    ];

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

public function findContactByEmail(string $email): ?array
{
    $email = trim(strtolower($email));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Invalid email address.');
    }

    $endpoint = sprintf(
        '/profiles/%s/contacts?search=%s&per_page=1',
        rawurlencode($this->profileUuid),
        rawurlencode($email)
    );

    $response = $this->request('GET', $endpoint);

    $contacts = $response['data'] ?? [];

    if (!is_array($contacts) || $contacts === []) {
        return null;
    }

    foreach ($contacts as $contact) {
        if (
            isset($contact['email']) &&
            strtolower((string) $contact['email']) === $email
        ) {
            return $contact;
        }
    }

    return null;
}

public function createContact(
    string $email,
    string $name,
    string $surname,
    ?string $phone = null
): array {
    $email = trim(strtolower($email));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Invalid email address.');
    }

    $payload = [
        'email'   => $email,
        'name'    => trim($name),
        'surname' => trim($surname),
    ];

    if ($phone !== null && trim($phone) !== '') {
        $payload['phone'] = trim($phone);
    }

    $endpoint = sprintf(
        '/profiles/%s/contacts',
        rawurlencode($this->profileUuid)
    );

    return $this->request('POST', $endpoint, $payload);
}

public function updateContact(
    string $contactUuid,
    array $data
): array {
    $contactUuid = trim($contactUuid);

    if ($contactUuid === '') {
        throw new InvalidArgumentException('Invalid Reach contact UUID.');
    }

    if ($data === []) {
        throw new InvalidArgumentException('No contact data provided.');
    }

    // Si se actualiza el email, lo normalizamos y validamos.
    if (array_key_exists('email', $data)) {
        $email = trim(strtolower((string) $data['email']));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email address.');
        }

        $data['email'] = $email;
    }

    $endpoint = sprintf(
        '/profiles/%s/contacts/%s',
        rawurlencode($this->profileUuid),
        rawurlencode($contactUuid)
    );

    return $this->request('PATCH', $endpoint, $data);
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