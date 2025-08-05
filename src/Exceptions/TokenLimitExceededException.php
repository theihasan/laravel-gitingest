<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Exceptions;

class TokenLimitExceededException extends GitIngestException
{
    public const ERROR_CODE_CONTENT_TOO_LARGE = 'TOKEN_CONTENT_TOO_LARGE';
    public const ERROR_CODE_MODEL_LIMIT_EXCEEDED = 'TOKEN_MODEL_LIMIT_EXCEEDED';
    public const ERROR_CODE_OPTIMIZATION_FAILED = 'TOKEN_OPTIMIZATION_FAILED';

    public static function contentTooLarge(int $tokenCount, int $limit, string $model): self
    {
        return (new self("Content contains {$tokenCount} tokens, exceeding the {$limit} token limit for model '{$model}'.", 413))
            ->setContext([
                'token_count' => $tokenCount,
                'limit' => $limit,
                'model' => $model,
                'error_code' => self::ERROR_CODE_CONTENT_TOO_LARGE,
            ]);
    }

    public static function modelLimitExceeded(string $model, int $tokenCount, int $maxTokens): self
    {
        return (new self("Model '{$model}' cannot process {$tokenCount} tokens. Maximum allowed: {$maxTokens}.", 413))
            ->setContext([
                'model' => $model,
                'token_count' => $tokenCount,
                'max_tokens' => $maxTokens,
                'error_code' => self::ERROR_CODE_MODEL_LIMIT_EXCEEDED,
            ]);
    }

    public static function optimizationFailed(string $content, string $reason): self
    {
        $contentLength = strlen($content);
        
        return (new self("Failed to optimize content ({$contentLength} characters): {$reason}", 500))
            ->setContext([
                'content_length' => $contentLength,
                'reason' => $reason,
                'error_code' => self::ERROR_CODE_OPTIMIZATION_FAILED,
            ]);
    }

    public function getTokenCount(): ?int
    {
        return $this->getContext()['token_count'] ?? null;
    }

    public function getTokenLimit(): ?int
    {
        return $this->getContext()['limit'] ?? $this->getContext()['max_tokens'] ?? null;
    }

    public function getModel(): ?string
    {
        return $this->getContext()['model'] ?? null;
    }
}
