<?php

namespace App\Services\InternalGames;

class BlackjackEngine
{
    private const SUITS = ['hearts', 'diamonds', 'clubs', 'spades'];
    private const RANKS = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];

    public function createRoundState(array $definition, float $betAmount): array
    {
        $deck = $this->shuffledDeck();
        $playerHand = [$this->drawCard($deck), $this->drawCard($deck)];
        $dealerHand = [$this->drawCard($deck), $this->drawCard($deck)];

        $state = [
            'engine' => 'blackjack-v1',
            'rules' => [
                'blackjack_payout_multiplier' => round((float) ($definition['blackjack_payout_multiplier'] ?? 2.5), 2),
                'dealer_hits_soft_17' => (bool) ($definition['dealer_hits_soft_17'] ?? false),
                'double_policy' => (string) ($definition['double_policy'] ?? 'any_two_cards'),
            ],
            'baseBet' => round($betAmount, 2),
            'additionalBet' => 0.0,
            'totalStake' => round($betAmount, 2),
            'playerHand' => $playerHand,
            'dealerHand' => $dealerHand,
            'remainingDeck' => $deck,
            'dealerRevealHoleCard' => false,
            'phase' => 'player_turn',
            'outcome' => null,
            'version' => 1,
            'decisionCount' => 0,
            'doubled' => false,
            'requestRegistry' => [],
            'actionTimeline' => [],
            'linkedTransactions' => [
                'bet' => null,
                'additionalDebits' => [],
                'settlement' => null,
                'refund' => null,
            ],
        ];

        $state['actionTimeline'][] = [
            'sequence' => 1,
            'type' => 'initial_deal',
            'player_cards' => $this->cardCodes($playerHand),
            'dealer_cards' => $this->cardCodes($dealerHand),
            'dealer_visible' => [$dealerHand[0]['code']],
            'created_at' => now()->toIso8601String(),
        ];

        return $this->refreshState($this->resolveInitialNaturals($state));
    }

    public function applyAction(array $state, string $action): array
    {
        $state = $this->refreshState($state);

        if (($state['phase'] ?? null) !== 'player_turn') {
            return $state;
        }

        $action = strtolower(trim($action));
        $nextSequence = count((array) ($state['actionTimeline'] ?? [])) + 1;

        if ($action === 'hit') {
            $drawnCard = $this->drawCard($state['remainingDeck']);
            $state['playerHand'][] = $drawnCard;
            $state['decisionCount'] = (int) ($state['decisionCount'] ?? 0) + 1;
            $state['actionTimeline'][] = [
                'sequence' => $nextSequence,
                'type' => 'hit',
                'card' => $drawnCard['code'],
                'created_at' => now()->toIso8601String(),
            ];

            $state = $this->refreshState($state);

            if (($state['playerSummary']['isBust'] ?? false) === true) {
                $state['dealerRevealHoleCard'] = true;

                return $this->finalizeState($state);
            }

            if ((int) ($state['playerSummary']['bestTotal'] ?? 0) === 21) {
                $state['dealerRevealHoleCard'] = true;
                $state = $this->playDealer($state);

                return $this->finalizeState($state);
            }

            return $state;
        }

        if ($action === 'stand') {
            $state['decisionCount'] = (int) ($state['decisionCount'] ?? 0) + 1;
            $state['dealerRevealHoleCard'] = true;
            $state['actionTimeline'][] = [
                'sequence' => $nextSequence,
                'type' => 'stand',
                'created_at' => now()->toIso8601String(),
            ];
            $state = $this->playDealer($state);

            return $this->finalizeState($state);
        }

        if ($action === 'double') {
            $state['decisionCount'] = (int) ($state['decisionCount'] ?? 0) + 1;
            $state['doubled'] = true;
            $state['additionalBet'] = round((float) ($state['baseBet'] ?? 0), 2);
            $state['totalStake'] = round((float) ($state['baseBet'] ?? 0) + (float) ($state['additionalBet'] ?? 0), 2);
            $drawnCard = $this->drawCard($state['remainingDeck']);
            $state['playerHand'][] = $drawnCard;
            $state['dealerRevealHoleCard'] = true;
            $state['actionTimeline'][] = [
                'sequence' => $nextSequence,
                'type' => 'double',
                'card' => $drawnCard['code'],
                'created_at' => now()->toIso8601String(),
            ];
            $state = $this->refreshState($state);

            if (($state['playerSummary']['isBust'] ?? false) !== true) {
                $state = $this->playDealer($state);
            }

            return $this->finalizeState($state);
        }

        return $state;
    }

    public function refreshState(array $state): array
    {
        $playerSummary = $this->handSummary((array) ($state['playerHand'] ?? []));
        $dealerSummary = $this->handSummary((array) ($state['dealerHand'] ?? []));
        $playerCards = count((array) ($state['playerHand'] ?? []));
        $canDouble = ($state['phase'] ?? null) === 'player_turn'
            && $playerCards === 2
            && !($state['doubled'] ?? false)
            && !($playerSummary['isBlackjack'] ?? false)
            && !($dealerSummary['isBlackjack'] ?? false);

        $state['playerSummary'] = $playerSummary;
        $state['dealerSummary'] = $dealerSummary;
        $state['canDouble'] = $canDouble;

        return $state;
    }

    public function buildResponse(array $state, bool $includeDealerHoleCard = false): array
    {
        $state = $this->refreshState($state);
        $dealerCards = (array) ($state['dealerHand'] ?? []);
        $revealDealer = $includeDealerHoleCard || (bool) ($state['dealerRevealHoleCard'] ?? false) || ($state['phase'] ?? null) === 'resolved';

        return [
            'roundVersion' => (int) ($state['version'] ?? 1),
            'phase' => (string) ($state['phase'] ?? 'player_turn'),
            'outcome' => $state['outcome'] ?? null,
            'baseBet' => round((float) ($state['baseBet'] ?? 0), 2),
            'additionalBet' => round((float) ($state['additionalBet'] ?? 0), 2),
            'totalStake' => round((float) ($state['totalStake'] ?? 0), 2),
            'payoutTotal' => round((float) ($state['payoutTotal'] ?? 0), 2),
            'net' => round((float) ($state['net'] ?? 0), 2),
            'player' => [
                'cards' => $this->cardsPayload((array) ($state['playerHand'] ?? [])),
                'summary' => $state['playerSummary'],
            ],
            'dealer' => [
                'cards' => $revealDealer
                    ? $this->cardsPayload($dealerCards)
                    : $this->maskedDealerCards($dealerCards),
                'summary' => $revealDealer
                    ? $state['dealerSummary']
                    : [
                        'visibleTotal' => $this->visibleDealerTotal($dealerCards),
                        'hiddenCount' => max(count($dealerCards) - 1, 0),
                    ],
            ],
            'allowedActions' => ($state['phase'] ?? null) === 'player_turn'
                ? array_values(array_filter([
                    'hit',
                    'stand',
                    ($state['canDouble'] ?? false) ? 'double' : null,
                ]))
                : [],
            'resultSummary' => (string) ($state['resultSummary'] ?? ''),
            'decisionCount' => (int) ($state['decisionCount'] ?? 0),
            'linkedTransactions' => $state['linkedTransactions'] ?? [],
            'actionTimeline' => array_values((array) ($state['actionTimeline'] ?? [])),
        ];
    }

    private function resolveInitialNaturals(array $state): array
    {
        $state = $this->refreshState($state);
        $playerBlackjack = (bool) ($state['playerSummary']['isBlackjack'] ?? false);
        $dealerBlackjack = (bool) ($state['dealerSummary']['isBlackjack'] ?? false);

        if (!$playerBlackjack && !$dealerBlackjack) {
            return $state;
        }

        $state['dealerRevealHoleCard'] = true;

        return $this->finalizeState($state);
    }

    private function playDealer(array $state): array
    {
        $dealerHitsSoft17 = (bool) data_get($state, 'rules.dealer_hits_soft_17', false);

        while (true) {
            $state = $this->refreshState($state);
            $dealerSummary = (array) ($state['dealerSummary'] ?? []);
            $dealerTotal = (int) ($dealerSummary['bestTotal'] ?? 0);
            $dealerSoft17 = $dealerTotal === 17 && ($dealerSummary['isSoft'] ?? false);

            if ($dealerTotal > 17) {
                break;
            }

            if ($dealerTotal === 17 && (!$dealerHitsSoft17 || !$dealerSoft17)) {
                break;
            }

            if ($dealerTotal >= 17 && !$dealerSoft17) {
                break;
            }

            $drawnCard = $this->drawCard($state['remainingDeck']);
            $state['dealerHand'][] = $drawnCard;
            $state['actionTimeline'][] = [
                'sequence' => count((array) ($state['actionTimeline'] ?? [])) + 1,
                'type' => 'dealer_hit',
                'card' => $drawnCard['code'],
                'created_at' => now()->toIso8601String(),
            ];
        }

        return $this->refreshState($state);
    }

    private function finalizeState(array $state): array
    {
        $state = $this->refreshState($state);
        $player = (array) ($state['playerSummary'] ?? []);
        $dealer = (array) ($state['dealerSummary'] ?? []);
        $baseBet = round((float) ($state['baseBet'] ?? 0), 2);
        $totalStake = round((float) ($state['totalStake'] ?? $baseBet), 2);
        $blackjackMultiplier = round((float) data_get($state, 'rules.blackjack_payout_multiplier', 2.5), 2);

        if (($player['isBust'] ?? false) === true) {
            $outcome = 'loss';
            $payoutTotal = 0.0;
            $summary = sprintf('Player busts with %d.', (int) ($player['bestTotal'] ?? 0));
        } elseif (($player['isBlackjack'] ?? false) === true && ($dealer['isBlackjack'] ?? false) !== true) {
            $outcome = 'blackjack';
            $payoutTotal = round($baseBet * $blackjackMultiplier, 2);
            $summary = sprintf('Blackjack pays %sx.', number_format($blackjackMultiplier, 2));
        } elseif (($dealer['isBlackjack'] ?? false) === true && ($player['isBlackjack'] ?? false) !== true) {
            $outcome = 'loss';
            $payoutTotal = 0.0;
            $summary = 'Dealer blackjack.';
        } elseif (($dealer['isBust'] ?? false) === true) {
            $outcome = 'win';
            $payoutTotal = round($totalStake * 2, 2);
            $summary = sprintf('Dealer busts with %d.', (int) ($dealer['bestTotal'] ?? 0));
        } else {
            $playerTotal = (int) ($player['bestTotal'] ?? 0);
            $dealerTotal = (int) ($dealer['bestTotal'] ?? 0);

            if ($playerTotal > $dealerTotal) {
                $outcome = 'win';
                $payoutTotal = round($totalStake * 2, 2);
                $summary = sprintf('Player %d beats dealer %d.', $playerTotal, $dealerTotal);
            } elseif ($playerTotal === $dealerTotal) {
                $outcome = 'push';
                $payoutTotal = round($totalStake, 2);
                $summary = sprintf('Push at %d.', $playerTotal);
            } else {
                $outcome = 'loss';
                $payoutTotal = 0.0;
                $summary = sprintf('Dealer %d beats player %d.', $dealerTotal, $playerTotal);
            }
        }

        $state['phase'] = 'resolved';
        $state['dealerRevealHoleCard'] = true;
        $state['outcome'] = $outcome;
        $state['payoutTotal'] = $payoutTotal;
        $state['net'] = round($payoutTotal - $totalStake, 2);
        $state['resultSummary'] = $summary;
        $state['actionTimeline'][] = [
            'sequence' => count((array) ($state['actionTimeline'] ?? [])) + 1,
            'type' => 'resolved',
            'outcome' => $outcome,
            'player_total' => (int) ($state['playerSummary']['bestTotal'] ?? 0),
            'dealer_total' => (int) ($state['dealerSummary']['bestTotal'] ?? 0),
            'payout_total' => $payoutTotal,
            'created_at' => now()->toIso8601String(),
        ];

        return $this->refreshState($state);
    }

    private function shuffledDeck(): array
    {
        $deck = [];

        foreach (self::SUITS as $suit) {
            foreach (self::RANKS as $rank) {
                $deck[] = [
                    'code' => $rank . strtoupper(substr($suit, 0, 1)),
                    'rank' => $rank,
                    'suit' => $suit,
                    'color' => in_array($suit, ['hearts', 'diamonds'], true) ? 'red' : 'black',
                    'value' => $this->rankValue($rank),
                ];
            }
        }

        // Cryptographically secure Fisher-Yates shuffle. random_int() is backed by
        // a CSPRNG, unlike PHP's shuffle() which is documented as not suitable for
        // cryptographic purposes. Do not replace with shuffle(), array_rand(),
        // or any mt_rand()-based primitive.
        for ($i = count($deck) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$deck[$i], $deck[$j]] = [$deck[$j], $deck[$i]];
        }

        return $deck;
    }

    private function drawCard(array &$deck): array
    {
        $card = array_shift($deck);

        return is_array($card) ? $card : [];
    }

    private function handSummary(array $cards): array
    {
        $totals = [0];

        foreach ($cards as $card) {
            $rank = (string) ($card['rank'] ?? '');
            $cardTotals = $rank === 'A' ? [1, 11] : [$this->rankValue($rank)];
            $nextTotals = [];

            foreach ($totals as $total) {
                foreach ($cardTotals as $value) {
                    $nextTotals[] = $total + $value;
                }
            }

            $totals = array_values(array_unique($nextTotals));
        }

        sort($totals);
        $validTotals = array_values(array_filter($totals, static fn ($total) => $total <= 21));
        $bestTotal = $validTotals !== [] ? max($validTotals) : min($totals ?: [0]);

        return [
            'bestTotal' => (int) $bestTotal,
            'allTotals' => array_values($totals),
            'isSoft' => in_array($bestTotal - 10, $validTotals, true),
            'isBlackjack' => count($cards) === 2 && $bestTotal === 21,
            'isBust' => $validTotals === [],
            'cardCount' => count($cards),
        ];
    }

    private function cardsPayload(array $cards): array
    {
        return array_map(function (array $card) {
            return [
                'code' => (string) ($card['code'] ?? ''),
                'rank' => (string) ($card['rank'] ?? ''),
                'suit' => (string) ($card['suit'] ?? ''),
                'color' => (string) ($card['color'] ?? 'black'),
            ];
        }, $cards);
    }

    private function maskedDealerCards(array $cards): array
    {
        $payload = [];

        foreach ($cards as $index => $card) {
            if ($index === 0) {
                $payload[] = [
                    'code' => (string) ($card['code'] ?? ''),
                    'rank' => (string) ($card['rank'] ?? ''),
                    'suit' => (string) ($card['suit'] ?? ''),
                    'color' => (string) ($card['color'] ?? 'black'),
                ];
                continue;
            }

            $payload[] = [
                'code' => 'hidden',
                'rank' => '?',
                'suit' => 'hidden',
                'color' => 'hidden',
                'hidden' => true,
            ];
        }

        return $payload;
    }

    private function visibleDealerTotal(array $cards): int
    {
        if ($cards === []) {
            return 0;
        }

        return (int) $this->handSummary([reset($cards)])['bestTotal'];
    }

    private function cardCodes(array $cards): array
    {
        return array_map(static fn (array $card) => (string) ($card['code'] ?? ''), $cards);
    }

    private function rankValue(string $rank): int
    {
        return match ($rank) {
            'A' => 11,
            'K', 'Q', 'J' => 10,
            default => (int) $rank,
        };
    }
}
