<?php
declare(strict_types=1);

namespace App\Finance;

use RuntimeException;

final class EfiClient
{
    private string $clientId;
    private string $clientSecret;
    private bool $sandbox;
    private ?string $certPath;
    private ?string $certPass;
    private ?string $accessToken = null;
    private ?int $tokenExpiresAt = null;

    public function __construct(array $config)
    {
        $this->clientId = $config['client_id'] ?? '';
        $this->clientSecret = $config['client_secret'] ?? '';
        $this->sandbox = (bool) ($config['sandbox'] ?? true);
        $this->certPath = $config['cert_path'] ?? null;
        $this->certPass = $config['cert_pass'] ?? null;
    }

    public function authenticate(): void
    {
        if ($this->accessToken !== null && $this->tokenExpiresAt !== null && $this->tokenExpiresAt > time() + 60) {
            return;
        }

        $endpoint = $this->sandbox ? 'https://api-pix-h.gerencianet.com.br' : 'https://api-pix.gerencianet.com.br';
        $url = $endpoint . '/oauth/token';

        $response = $this->request('POST', $url, [
            'grant_type' => 'client_credentials',
        ], [
            CURLOPT_USERPWD => $this->clientId . ':' . $this->clientSecret,
        ]);

        if (!isset($response['access_token'])) {
            throw new RuntimeException('Falha ao autenticar na EFI');
        }

        $this->accessToken = $response['access_token'];
        $this->tokenExpiresAt = time() + (int) ($response['expires_in'] ?? 3600);
    }

    public function createChargePix(array $payload): array
    {
        $this->authenticate();
        $endpoint = $this->sandbox ? 'https://api-pix-h.gerencianet.com.br' : 'https://api-pix.gerencianet.com.br';
        $url = $endpoint . '/v2/cob';
        return $this->request('POST', $url, $payload, $this->authHeaders());
    }

    public function createChargeBoleto(array $payload): array
    {
        $this->authenticate();
        $endpoint = $this->sandbox ? 'https://apisandbox.gerencianet.com.br' : 'https://api.gerencianet.com.br';
        $url = $endpoint . '/v1/charge';
        return $this->request('POST', $url, $payload, $this->authHeaders());
    }

    public function getChargeStatus(string $chargeId): array
    {
        $this->authenticate();
        $endpoint = $this->sandbox ? 'https://api-pix-h.gerencianet.com.br' : 'https://api-pix.gerencianet.com.br';
        $url = $endpoint . '/v2/cob/' . urlencode($chargeId);
        return $this->request('GET', $url, [], $this->authHeaders());
    }

    private function authHeaders(): array
    {
        return [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json',
            ],
        ];
    }

    private function request(string $method, string $url, array $payload = [], array $options = []): array
    {
        $ch = curl_init();
        if ($ch === false) {
            throw new RuntimeException('Não foi possível inicializar o cURL');
        }

        $defaultOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ];

        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            $defaultOptions[CURLOPT_CUSTOMREQUEST] = $method;
            $defaultOptions[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $defaultOptions[CURLOPT_HTTPHEADER] = ['Content-Type: application/json'];
        } elseif ($method === 'GET' && $payload) {
            $defaultOptions[CURLOPT_URL] = $url . '?' . http_build_query($payload);
        }

        if ($this->certPath) {
            $defaultOptions[CURLOPT_SSLCERT] = $this->certPath;
            if ($this->certPass) {
                $defaultOptions[CURLOPT_SSLCERTPASSWD] = $this->certPass;
            }
        }

        foreach ($options as $key => $value) {
            $defaultOptions[$key] = $value;
        }

        curl_setopt_array($ch, $defaultOptions);
        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Erro na requisição EFI: ' . $error);
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Resposta inválida da EFI: ' . substr((string) $response, 0, 200));
        }

        if ($status >= 400) {
            throw new RuntimeException('Erro EFI (' . $status . '): ' . json_encode($decoded, JSON_UNESCAPED_UNICODE));
        }

        return $decoded;
    }
}
