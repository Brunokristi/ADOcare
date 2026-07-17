<?php

namespace App\Services\Vertex;

use Illuminate\Support\Facades\Http;

class VertexAuthService
{
    private const VERTEX_SCOPE = 'https://www.googleapis.com/auth/cloud-platform';

    public function getAccessToken(string $credentialsPath): string
    {
        if (! is_file($credentialsPath) || ! is_readable($credentialsPath)) {
            throw new \RuntimeException('Súbor so service account JSON pre Vertex AI sa nenašiel.');
        }

        $json = json_decode((string) file_get_contents($credentialsPath), true);

        if (! is_array($json)) {
            throw new \RuntimeException('Service account JSON je neplatný.');
        }

        $clientEmail = (string) ($json['client_email'] ?? '');
        $privateKey = (string) ($json['private_key'] ?? '');

        if ($clientEmail === '' || $privateKey === '') {
            throw new \RuntimeException('Service account JSON neobsahuje client_email alebo private_key.');
        }

        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES));
        $claimSet = $this->base64UrlEncode(json_encode([
            'iss' => $clientEmail,
            'sub' => $clientEmail,
            'scope' => self::VERTEX_SCOPE,
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_UNESCAPED_SLASHES));

        $unsignedJwt = $header . '.' . $claimSet;
        $signature = '';

        if (! openssl_sign($unsignedJwt, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Nepodarilo sa podpísať JWT pre Vertex AI.');
        }

        $jwt = $unsignedJwt . '.' . $this->base64UrlEncode($signature);

        $response = Http::asForm()
            ->timeout(30)
            ->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Nepodarilo sa získať Vertex access token.');
        }

        $token = trim((string) data_get($response->json(), 'access_token', ''));

        if ($token === '') {
            throw new \RuntimeException('Vertex access token nebol vrátený.');
        }

        return $token;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
