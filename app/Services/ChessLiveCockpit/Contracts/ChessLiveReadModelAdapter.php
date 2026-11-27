<?php

namespace App\Services\ChessLiveCockpit\Contracts;

interface ChessLiveReadModelAdapter
{
    /**
     * @return array{
     *     event: array<string, mixed>,
     *     boards: array<int, array<string, mixed>>
     * }
     */
    public function fetchActiveEventSnapshot(): array;
}
