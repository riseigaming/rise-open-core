<?php

namespace App\Services\ChessLiveCockpit;

use App\Models\ChessLiveBoard;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class ChessBettingMarketTemplateCatalog
{
    public function availableForBoard(ChessLiveBoard $board): Collection
    {
        $templates = collect();
        $meta = (array) $board->meta;

        $templates->push($this->matchResultTemplate($board));
        $templates->push($this->decisiveTemplate($board));

        if ((bool) Arr::get($meta, 'supports_player_qualifies', false)) {
            $templates = $templates->merge($this->perPlayerYesNoTemplates(
                $board,
                'player_qualifies_yes_no',
                'Player Qualifies Yes/No',
                'qualifies'
            ));
        }

        if ((bool) Arr::get($meta, 'supports_top3', false)) {
            $templates = $templates->merge($this->perPlayerYesNoTemplates(
                $board,
                'top_3_yes_no',
                'Top 3 Yes/No',
                'finishes top 3'
            ));
        }

        if ((bool) Arr::get($meta, 'supports_mini_match', false)) {
            $templates = $templates->merge($this->perPlayerYesNoTemplates(
                $board,
                'mini_match_qualifies_yes_no',
                'Mini Match Qualifies Yes/No',
                'wins mini match qualification'
            ));
            $templates->push($this->miniMatchWinnerTemplate($board));
        }

        return $templates->values();
    }

    public function findOrFail(ChessLiveBoard $board, string $templateKey): array
    {
        $template = $this->availableForBoard($board)->firstWhere('key', $templateKey);

        if (!$template) {
            abort(404, 'Template is not available for this board.');
        }

        return $template;
    }

    private function matchResultTemplate(ChessLiveBoard $board): array
    {
        return [
            'key' => 'match_result_1x2',
            'template_type' => 'match_result_1x2',
            'template_variant' => 'default',
            'scope_type' => 'board',
            'group_label' => 'Match Result 1X2',
            'title' => $board->white_player_name . ' vs ' . $board->black_player_name . ' Match Result',
            'description' => 'Three-way result market for the current board.',
            'selections' => [
                ['code' => 'home', 'label' => $board->white_player_name],
                ['code' => 'draw', 'label' => 'Draw'],
                ['code' => 'away', 'label' => $board->black_player_name],
            ],
            'result_hint_key' => 'match_result_1x2',
        ];
    }

    private function decisiveTemplate(ChessLiveBoard $board): array
    {
        return [
            'key' => 'decisive_yes_no',
            'template_type' => 'decisive_yes_no',
            'template_variant' => 'default',
            'scope_type' => 'board',
            'group_label' => 'Decisive Yes/No',
            'title' => 'Decisive Result on Board ' . $board->board_no,
            'description' => 'Yes means the game does not finish drawn.',
            'selections' => [
                ['code' => 'yes', 'label' => 'Yes'],
                ['code' => 'no', 'label' => 'No'],
            ],
            'result_hint_key' => 'decisive_yes_no',
        ];
    }

    private function miniMatchWinnerTemplate(ChessLiveBoard $board): array
    {
        return [
            'key' => 'mini_match_winner',
            'template_type' => 'mini_match_winner',
            'template_variant' => 'default',
            'scope_type' => 'board',
            'group_label' => 'Mini Match Winner',
            'title' => 'Mini Match Winner for Board ' . $board->board_no,
            'description' => 'Winner market for the configured mini match context.',
            'selections' => [
                ['code' => 'home', 'label' => $board->white_player_name],
                ['code' => 'draw', 'label' => 'Draw'],
                ['code' => 'away', 'label' => $board->black_player_name],
            ],
            'result_hint_key' => 'mini_match_winner',
        ];
    }

    private function perPlayerYesNoTemplates(
        ChessLiveBoard $board,
        string $templateType,
        string $groupLabel,
        string $verbPhrase
    ): Collection {
        $meta = (array) $board->meta;
        $targetMap = [];

        if ($templateType === 'player_qualifies_yes_no') {
            $targetMap = (array) Arr::get($meta, 'qualification_targets', []);
        } elseif ($templateType === 'top_3_yes_no') {
            $targetMap = (array) Arr::get($meta, 'top3_targets', []);
        } elseif ($templateType === 'mini_match_qualifies_yes_no') {
            $targetMap = (array) Arr::get($meta, 'qualification_targets', []);
        }

        $players = [
            'white' => $board->white_player_name,
            'black' => $board->black_player_name,
        ];

        return collect($players)->filter(function ($playerName, $side) use ($targetMap) {
            return (bool) ($targetMap[$side] ?? false);
        })->map(function ($playerName, $side) use ($board, $templateType, $groupLabel, $verbPhrase) {
            return [
                'key' => $templateType . ':' . $side,
                'template_type' => $templateType,
                'template_variant' => $side,
                'scope_type' => 'board_player',
                'group_label' => $groupLabel,
                'title' => $playerName . ' ' . ucfirst($verbPhrase),
                'description' => 'Binary market tied to the ' . $playerName . ' board context.',
                'selections' => [
                    ['code' => 'yes', 'label' => 'Yes'],
                    ['code' => 'no', 'label' => 'No'],
                ],
                'player_side' => $side,
                'player_name' => $playerName,
                'result_hint_key' => $templateType . ':' . $side,
            ];
        })->values();
    }
}
