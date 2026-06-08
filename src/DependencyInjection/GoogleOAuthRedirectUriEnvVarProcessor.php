<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

/**
 * Normalise GOOGLE_OAUTH_REDIRECT_URI (supprime le double slash avant /connect, etc.).
 */
final class GoogleOAuthRedirectUriEnvVarProcessor implements EnvVarProcessorInterface
{
    public static function getProvidedTypes(): array
    {
        return ['ef_google_oauth_redirect' => 'string'];
    }

    public function getEnv(string $prefix, string $name, \Closure $getEnv): string
    {
        $raw = trim((string) $getEnv($name));
        if ('' === $raw) {
            $base = rtrim(trim((string) $getEnv('DEFAULT_URI')), '/');
            if ('' === $base) {
                throw new \RuntimeException('GOOGLE_OAUTH_REDIRECT_URI ou DEFAULT_URI doit être renseigné.');
            }
            $raw = $base.'/connect/google/check';
        }

        return self::normalize($raw);
    }

    public static function normalize(string $uri): string
    {
        $uri = trim($uri);
        $parts = parse_url($uri);
        if (!\is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new \InvalidArgumentException(sprintf('URI OAuth Google invalide : %s', $uri));
        }

        $path = $parts['path'] ?? '/connect/google/check';
        $path = '/'.ltrim(preg_replace('#/+#', '/', $path) ?? $path, '/');

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $parts['scheme'].'://'.$parts['host'].$port.$path;
    }
}
