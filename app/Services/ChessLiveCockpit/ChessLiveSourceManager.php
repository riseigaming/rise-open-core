<?php

namespace App\Services\ChessLiveCockpit;

use App\Services\ChessLiveCockpit\Contracts\ChessLiveReadModelAdapter;
use RuntimeException;

class ChessLiveSourceManager
{
    public function adapter(): ChessLiveReadModelAdapter
    {
        $sourceKey = (string) config('chess_live_cockpit.default_source', 'fake');
        $className = config('chess_live_cockpit.sources.' . $sourceKey . '.adapter');

        if (!is_string($className) || $className === '') {
            throw new RuntimeException('Chess live cockpit adapter is not configured for source [' . $sourceKey . '].');
        }

        $adapter = app($className);

        if (!$adapter instanceof ChessLiveReadModelAdapter) {
            throw new RuntimeException('Configured chess live cockpit adapter [' . $className . '] is invalid.');
        }

        return $adapter;
    }
}
