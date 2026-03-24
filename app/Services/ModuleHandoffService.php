<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use Exception;

final class ModuleHandoffService
{
    public function __construct(private Config $config)
    {
    }

    /**
     * @param array<string, mixed>|null $authUser
     * @return array{url: string, token_issued: bool, token_expires_at: ?string}
     */
    public function buildExternalRedirect(string $externalUrl, string $moduleSlug, ?array $authUser): array
    {
        $cleanUrl = $this->sanitizeExternalUrl($externalUrl);
        if ($cleanUrl === null) {
            return [
                'url' => $externalUrl,
                'token_issued' => false,
                'token_expires_at' => null,
            ];
        }

        $queryParams = [
            'source' => 'guardachuva',
            'module' => $moduleSlug,
        ];

        $secret = trim((string) $this->config->get('modules.external.handoff_secret', ''));
        if ($secret === '' || $authUser === null) {
            return [
                'url' => $this->withQueryParams($cleanUrl, $queryParams),
                'token_issued' => false,
                'token_expires_at' => null,
            ];
        }

        $ttl = (int) $this->config->get('modules.external.handoff_ttl_seconds', 120);
        $ttl = max(30, min(900, $ttl));
        $issuedAt = time();
        $expiresAt = $issuedAt + $ttl;

        $payload = [
            'iss' => (string) $this->config->get('app.base_url', ''),
            'aud' => 'module:' . $moduleSlug,
            'sub' => (int) ($authUser['id'] ?? 0),
            'name' => (string) ($authUser['name'] ?? ''),
            'email' => (string) ($authUser['email'] ?? ''),
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'nonce' => $this->nonce(),
        ];

        $token = $this->signPayload($payload, $secret);
        $queryParams['handoff_token'] = $token;

        return [
            'url' => $this->withQueryParams($cleanUrl, $queryParams),
            'token_issued' => true,
            'token_expires_at' => date('c', $expiresAt),
        ];
    }

    private function sanitizeExternalUrl(string $url): ?string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return null;
        }

        $parts = parse_url($trimmed);
        if ($parts === false) {
            return null;
        }

        $scheme = mb_strtolower(trim((string) ($parts['scheme'] ?? '')));
        $host = trim((string) ($parts['host'] ?? ''));
        if (!in_array($scheme, ['https', 'http'], true) || $host === '') {
            return null;
        }

        return $trimmed;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function signPayload(array $payload, string $secret): string
    {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($payloadJson) || $payloadJson === '') {
            $payloadJson = '{}';
        }

        $payloadEncoded = $this->base64UrlEncode($payloadJson);
        $signature = hash_hmac('sha256', $payloadEncoded, $secret, true);
        $signatureEncoded = $this->base64UrlEncode($signature);

        return $payloadEncoded . '.' . $signatureEncoded;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function nonce(): string
    {
        try {
            return bin2hex(random_bytes(10));
        } catch (Exception) {
            return substr(sha1((string) microtime(true)), 0, 20);
        }
    }

    /**
     * @param array<string, string> $queryParams
     */
    private function withQueryParams(string $url, array $queryParams): string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }

        $query = [];
        if (isset($parts['query']) && trim((string) $parts['query']) !== '') {
            parse_str((string) $parts['query'], $query);
        }

        foreach ($queryParams as $key => $value) {
            $query[$key] = $value;
        }

        $rebuilt = '';
        $scheme = (string) ($parts['scheme'] ?? '');
        if ($scheme !== '') {
            $rebuilt .= $scheme . '://';
        }

        $user = (string) ($parts['user'] ?? '');
        $pass = (string) ($parts['pass'] ?? '');
        if ($user !== '') {
            $rebuilt .= $user;
            if ($pass !== '') {
                $rebuilt .= ':' . $pass;
            }
            $rebuilt .= '@';
        }

        $host = (string) ($parts['host'] ?? '');
        $rebuilt .= $host;

        if (isset($parts['port'])) {
            $rebuilt .= ':' . (int) $parts['port'];
        }

        $path = (string) ($parts['path'] ?? '');
        $rebuilt .= $path;

        if ($query !== []) {
            $rebuilt .= '?' . http_build_query($query);
        }

        if (isset($parts['fragment']) && (string) $parts['fragment'] !== '') {
            $rebuilt .= '#' . (string) $parts['fragment'];
        }

        return $rebuilt;
    }
}
