<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Exceptions;

class AuthenticationException extends GitIngestException
{
    public const ERROR_CODE_MISSING_TOKEN = 'AUTH_MISSING_TOKEN';
    public const ERROR_CODE_INVALID_TOKEN = 'AUTH_INVALID_TOKEN';
    public const ERROR_CODE_EXPIRED_TOKEN = 'AUTH_EXPIRED_TOKEN';
    public const ERROR_CODE_INSUFFICIENT_PERMISSIONS = 'AUTH_INSUFFICIENT_PERMISSIONS';

    public static function missingToken(): self
    {
        return (new self('GitHub token is required for this operation.', 401))
            ->setContext(['error_code' => self::ERROR_CODE_MISSING_TOKEN]);
    }

    public static function invalidToken(string $token): self
    {
        return (new self('The provided GitHub token is invalid.', 401))
            ->setContext([
                'token' => substr($token, 0, 10) . '...',
                'error_code' => self::ERROR_CODE_INVALID_TOKEN,
            ]);
    }

    public static function expiredToken(): self
    {
        return (new self('The GitHub token has expired. Please generate a new token.', 401))
            ->setContext(['error_code' => self::ERROR_CODE_EXPIRED_TOKEN]);
    }

    public static function insufficientPermissions(array $requiredScopes = []): self
    {
        $message = 'Insufficient permissions for this operation.';
        if (!empty($requiredScopes)) {
            $message .= ' Required scopes: ' . implode(', ', $requiredScopes);
        }

        return (new self($message, 403))
            ->setContext([
                'required_scopes' => $requiredScopes,
                'error_code' => self::ERROR_CODE_INSUFFICIENT_PERMISSIONS,
            ]);
    }
}
