<?php

namespace App\Services\ExternalGames;

use RuntimeException;
use Throwable;

class ExternalGameProviderLaunchException extends RuntimeException
{
    public const BLOCKED_GEO = 'BLOCKED_GEO';

    public const BLOCKED_CURRENCY = 'BLOCKED_CURRENCY';

    public const PROVIDER_ERROR = 'PROVIDER_ERROR';

    public const INTEGRATION_ERROR = 'INTEGRATION_ERROR';

    private readonly array $safeDetails;

    public function __construct(string $message, array $safeDetails, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);

        $this->safeDetails = ExternalGameProviderFailureSanitizer::sanitizeStoredDetails($safeDetails);
    }

    public function safeDetails(): array
    {
        return $this->safeDetails;
    }

    public function withContext(array $context): self
    {
        $safeContext = ExternalGameProviderFailureSanitizer::sanitizeContext($context);

        return new self(
            $this->getMessage(),
            array_filter(
                array_merge($safeContext, $this->safeDetails),
                static fn (mixed $value): bool => $value !== null && $value !== ''
            ),
            $this->getPrevious()
        );
    }
}
