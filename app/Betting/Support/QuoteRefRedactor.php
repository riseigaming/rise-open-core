<?php

namespace App\Betting\Support;

class QuoteRefRedactor
{
    public function hash(?string $quoteRef): ?string
    {
        $quoteRef = trim((string) $quoteRef);

        return $quoteRef !== '' ? hash('sha256', $quoteRef) : null;
    }

    public function shortHash(?string $quoteRef): string
    {
        $hash = $this->hash($quoteRef);

        return $hash !== null ? substr($hash, 0, 12) : '-';
    }

    public function label(?string $quoteRef): string
    {
        $shortHash = $this->shortHash($quoteRef);

        return $shortHash !== '-' ? 'quote#' . $shortHash : '-';
    }
}
