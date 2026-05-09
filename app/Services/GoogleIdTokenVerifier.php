<?php

namespace App\Services;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class GoogleIdTokenVerifier
{
    /**
     * Verify a Google ID token and return the trusted payload fields.
     *
     * @return array<string, mixed>
     */
    public function verify(string $idToken): array
    {
        $idToken = trim($idToken);

        if ($idToken === '') {
            $this->invalid('Token Google wajib diisi.');
        }

        $allowedClientIds = $this->allowedClientIds();

        if ($allowedClientIds === []) {
            throw new RuntimeException('GOOGLE_ALLOWED_CLIENT_IDS belum dikonfigurasi.');
        }

        try {
            $decoded = (array) JWT::decode($idToken, JWK::parseKeySet($this->keySet()));
        } catch (ExpiredException) {
            $this->invalid('Token Google sudah kedaluwarsa.');
        } catch (Throwable $e) {
            $this->rethrowIfInfrastructureError($e);
            $this->invalid('Token Google tidak valid.');
        }

        $issuer = $decoded['iss'] ?? null;
        if (! in_array($issuer, ['https://accounts.google.com', 'accounts.google.com'], true)) {
            $this->invalid('Issuer Google tidak valid.');
        }

        $audiences = $this->normalizeToStringArray($decoded['aud'] ?? null);
        if ($audiences === [] || array_intersect($allowedClientIds, $audiences) === []) {
            $this->invalid('Audience Google tidak diizinkan.');
        }

        $email = $decoded['email'] ?? null;
        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->invalid('Email Google tidak tersedia.');
        }

        if (! $this->isEmailVerified($decoded['email_verified'] ?? false)) {
            $this->invalid('Email Google belum terverifikasi.');
        }

        return [
            'sub' => (string) ($decoded['sub'] ?? ''),
            'email' => mb_strtolower($email),
            'email_verified' => true,
            'name' => is_string($decoded['name'] ?? null) ? $decoded['name'] : null,
            'picture' => is_string($decoded['picture'] ?? null) ? $decoded['picture'] : null,
            'iss' => $issuer,
            'aud' => count($audiences) === 1 ? $audiences[0] : $audiences,
            'exp' => $decoded['exp'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function keySet(): array
    {
        $cacheKey = 'google_id_token_jwks';

        return Cache::remember($cacheKey, now()->addHour(), function (): array {
            $response = Http::acceptJson()
                ->timeout(10)
                ->get(config('services.google.jwks_url', 'https://www.googleapis.com/oauth2/v3/certs'));

            if (! $response->successful()) {
                throw new RuntimeException('Gagal mengambil public key Google untuk verifikasi token.');
            }

            $payload = $response->json();

            if (! is_array($payload) || ! isset($payload['keys']) || ! is_array($payload['keys'])) {
                throw new RuntimeException('Respons public key Google tidak valid.');
            }

            return $payload;
        });
    }

    /**
     * @return array<int, string>
     */
    protected function allowedClientIds(): array
    {
        $configured = config('services.google.allowed_client_ids', []);

        if (is_string($configured)) {
            $configured = explode(',', $configured);
        }

        if (! is_array($configured)) {
            return [];
        }

        $clientIds = array_map(
            static fn ($value) => is_string($value) ? trim($value) : '',
            $configured
        );

        return array_values(array_unique(array_filter($clientIds)));
    }

    protected function isEmailVerified(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (! is_string($value)) {
            return false;
        }

        return in_array(mb_strtolower(trim($value)), ['1', 'true', 'yes'], true);
    }

    /**
     * @return array<int, string>
     */
    protected function normalizeToStringArray(mixed $value): array
    {
        if (is_string($value)) {
            $value = [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        $items = array_map(
            static fn ($item) => is_string($item) ? trim($item) : '',
            $value
        );

        return array_values(array_filter($items));
    }

    protected function rethrowIfInfrastructureError(Throwable $e): void
    {
        if ($e instanceof RuntimeException) {
            throw $e;
        }
    }

    protected function invalid(string $message): never
    {
        throw ValidationException::withMessages([
            'id_token' => [$message],
        ]);
    }
}
