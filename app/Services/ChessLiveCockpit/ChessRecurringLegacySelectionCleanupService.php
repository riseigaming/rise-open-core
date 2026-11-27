<?php

namespace App\Services\ChessLiveCockpit;

use App\Models\ChessBettingMarket;
use App\Models\ChessBettingMarketSelection;
use App\Models\ChessLiveBoard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChessRecurringLegacySelectionCleanupService
{
    private const LEGACY_SELECTION_CODES = ['home', 'draw', 'away'];

    /**
     * Report or remove only obsolete selections on explicitly enabled recurring boards.
     *
     * @return array{status: string, dry_run: bool, eligible_market_count: int, eligible_selection_count: int, deleted_selection_count: int}
     */
    public function cleanup(bool $execute = false): array
    {
        $selections = $this->eligibleSelections();

        $summary = [
            'status' => $execute ? 'completed' : 'dry_run',
            'dry_run' => !$execute,
            'eligible_market_count' => $selections
                ->pluck('chess_betting_market_id')
                ->unique()
                ->count(),
            'eligible_selection_count' => $selections->count(),
            'deleted_selection_count' => 0,
        ];

        if (!$execute || $selections->isEmpty()) {
            return $summary;
        }

        DB::transaction(function () use ($selections, &$summary): void {
            foreach ($selections as $selection) {
                $summary['deleted_selection_count'] += $selection->delete() ? 1 : 0;
            }
        });

        return $summary;
    }

    /**
     * Do not use a broad delete: each candidate must pass every recurring-market guard.
     *
     * @return Collection<int, ChessBettingMarketSelection>
     */
    private function eligibleSelections(): Collection
    {
        if (!$this->requiredTablesExist()) {
            return collect();
        }

        return ChessBettingMarketSelection::query()
            ->with('market.board')
            ->whereIn('selection_code', self::LEGACY_SELECTION_CODES)
            ->whereHas('market', function ($query): void {
                $query->where('template_variant', 'recurring');
            })
            ->get()
            ->filter(function (ChessBettingMarketSelection $selection): bool {
                $market = $selection->market;
                $board = $market?->board;

                return $market instanceof ChessBettingMarket
                    && $board instanceof ChessLiveBoard
                    && (string) $market->template_variant === 'recurring'
                    && (bool) data_get($board->meta, 'recurring.enabled', false);
            })
            ->values();
    }

    private function requiredTablesExist(): bool
    {
        foreach ([
            'chess_betting_market_selections',
            'chess_betting_markets',
            'chess_live_boards',
        ] as $table) {
            if (!Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }
}
