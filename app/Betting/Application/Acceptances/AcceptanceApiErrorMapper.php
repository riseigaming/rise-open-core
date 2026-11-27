<?php

namespace App\Betting\Application\Acceptances;

use App\Betting\Exceptions\BettingDomainException;
use App\Exceptions\ProductAccess\ProductAccessDeniedException;
use Illuminate\Support\Facades\Log;
use Throwable;

class AcceptanceApiErrorMapper
{
    public function map(Throwable $exception): array
    {
        if ($exception instanceof ProductAccessDeniedException) {
            $details = $exception->details();
            $details['ui'] = $this->uiHintsFor($exception->errorCode(), $details);

            return [
                'status' => $exception->getStatusCode(),
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'details' => $details,
            ];
        }

        if ($exception instanceof BettingDomainException) {
            $details = $exception->details();
            $ui = $this->uiHintsFor($exception->errorCode(), $details);
            $details['ui'] = $ui;

            if ($this->isAdapterVpsError($exception->errorCode())) {
                unset($details['quote_ref']);
            }

            return [
                'status' => $exception->httpStatus(),
                'code' => $exception->errorCode(),
                'message' => $this->isAdapterVpsError($exception->errorCode())
                    ? $ui['message']
                    : $exception->getMessage(),
                'details' => $details,
            ];
        }

        Log::warning('Betting acceptance failed with a non-domain exception.', [
            'exception_class' => get_class($exception),
            'exception_code' => $exception->getCode(),
            'exception_message' => $exception->getMessage(),
        ]);

        return [
            'status' => 422,
            'code' => 'ACCEPTANCE_REJECTED',
            'message' => 'Unable to accept this selection right now.',
            'details' => [
                'ui' => $this->uiHintsFor('ACCEPTANCE_REJECTED', []),
            ],
        ];
    }

    private function uiHintsFor(string $code, array $details): array
    {
        if ($this->isAdapterVpsError($code)) {
            return $this->adapterVpsUiHints($code, (string) ($details['reason_code'] ?? ''));
        }

        return match ($code) {
            'INSUFFICIENT_BALANCE' => [
                'title' => 'Yetersiz bakiye',
                'message' => 'Bahis tutari mevcut bakiyeni asiyor. Tutarı dusur veya para yatirip tekrar dene.',
                'suggested_action' => 'deposit_or_lower_stake',
            ],
            'STAKE_LIMIT_EXCEEDED' => [
                'title' => 'Kupon limiti aşıldı',
                'message' => sprintf(
                    'Bu cüzdan için tek kupon tutarı en fazla %s. Tutarı düşürüp tekrar deneyin.',
                    (string) ($details['max_stake_formatted'] ?? 'belirlenen limit')
                ),
                'suggested_action' => 'lower_stake',
            ],
            'STALE_QUOTE_VERSION', 'STALE_PRICE', 'QUOTE_SELECTION_MISMATCH', 'QUOTE_SELECTION_NOT_FOUND' => [
                'title' => 'Oran guncellendi',
                'message' => 'Sectigin oran artik gecerli degil. Guncel teklif yuklendikten sonra kuponu yeniden onayla.',
                'suggested_action' => 'refresh_quote',
            ],
            'QUOTE_NOT_TRADEABLE', 'SELECTION_NOT_ACTIVE', 'FRONT_BETTING_DISABLED' => [
                'title' => 'Bahis su anda kapali',
                'message' => 'Bu market su anda kabul edilmiyor. Mac veya market durumu degismis olabilir.',
                'suggested_action' => 'pick_another_selection',
            ],
            'MARKET_NOT_BETTABLE' => [
                'title' => 'Market henuz bahis kabul etmiyor',
                'message' => 'Bu market gorunur durumda ama settlement rollout henuz acik degil. Baska bir secim deneyin.',
                'suggested_action' => 'pick_another_selection',
            ],
            'UNSUPPORTED_ACTIVE_WALLET' => [
                'title' => trans('wallet_playability.unsupported_wallet'),
                'message' => $details['message'] ?? trans('wallet_playability.unsupported_wallet'),
                'suggested_action' => $details['suggested_action'] ?? 'none',
            ],
            'PRODUCT_ACCESS_DENIED' => [
                'title' => 'Erişim kapalı',
                'message' => 'Bu ürün hesabınız için şu anda kullanılamıyor.',
                'suggested_action' => 'contact_support',
            ],
            'EVENT_CUTOFF_REACHED' => [
                'title' => 'Baslangic saati yaklasti',
                'message' => 'Etkinlik baslangicina cok az kaldigi icin bu bahis artik kabul edilmiyor.',
                'suggested_action' => 'pick_another_selection',
            ],
            'SOCIAL_MARKET_CLOSED', 'SOCIAL_MARKET_OUTCOME_CLOSED' => [
                'title' => 'Sosyal market kapalı',
                'message' => 'Bu sosyal market şu anda bahis kabul etmiyor. Lütfen başka bir seçim deneyin.',
                'suggested_action' => 'pick_another_selection',
            ],
            'SOCIAL_MARKET_TOKEN_MISSING', 'SOCIAL_MARKET_TOKEN_INVALID', 'SOCIAL_MARKET_TOKEN_EXPIRED', 'SOCIAL_MARKET_TOKEN_MISMATCH', 'SOCIAL_MARKET_SELECTION_NOT_FOUND', 'SOCIAL_MARKET_PRICE_CHANGED' => [
                'title' => 'Sosyal market güncellendi',
                'message' => 'Seçtiğin sosyal market oranı artık geçerli değil. Lütfen seçimi yeniden ekle.',
                'suggested_action' => 'pick_another_selection',
            ],
            'SOCIAL_MARKET_STAKE_TOO_LOW', 'SOCIAL_MARKET_STAKE_TOO_HIGH' => [
                'title' => 'Tutar uygun değil',
                'message' => 'Bu sosyal market için tutar limitleri uygun değil. Tutarı değiştirip tekrar deneyin.',
                'suggested_action' => 'lower_stake',
            ],
            'IDEMPOTENCY_CONFLICT' => [
                'title' => 'Istek yenilenmeli',
                'message' => 'Ayni istek farkli bilgilerle tekrarlandigi icin kupon guvenlik nedeniyle durduruldu. Secimi yenileyip tekrar dene.',
                'suggested_action' => 'refresh_selection',
            ],
            'QUOTE_CONTEXT_MISSING' => [
                'title' => 'Teklif hazir degil',
                'message' => 'Bu secim icin gerekli oran bilgisi eksik. Lutfen baska bir secim deneyin.',
                'suggested_action' => 'pick_another_selection',
            ],
            default => [
                'title' => 'Islem tamamlanamadi',
                'message' => 'Kupon su anda tamamlanamadi. Birkac saniye sonra tekrar deneyebilirsin.',
                'suggested_action' => 'retry',
            ],
        };
    }

    private function isAdapterVpsError(string $code): bool
    {
        return str_starts_with($code, 'ADAPTER_VPS_');
    }

    private function adapterVpsUiHints(string $code, string $reasonCode): array
    {
        if ($code === 'ADAPTER_VPS_ATTEMPT_RATE_LIMITED') {
            return [
                'title' => 'İşlem geçici olarak sınırlandı',
                'message' => 'Çok kısa süre içinde çok fazla deneme yapıldı. Lütfen biraz sonra tekrar deneyin.',
                'suggested_action' => 'retry_later',
            ];
        }

        if ($code === 'ADAPTER_VPS_ACCEPTANCE_DISABLED') {
            return [
                'title' => 'Kupon kabulü kapalı',
                'message' => 'Bu seçim için kupon kabulü şu anda kapalı.',
                'suggested_action' => 'pick_another_selection',
            ];
        }

        if (in_array($reasonCode, ['suspended', 'closed'], true)) {
            return [
                'title' => 'Bahis alımı kapalı',
                'message' => 'Bu seçim için bahis alımı şu anda kapalı.',
                'suggested_action' => 'pick_another_selection',
            ];
        }

        if (in_array($reasonCode, ['timeout', 'unavailable', 'auth_failed'], true)) {
            return [
                'title' => 'Teklif kontrol edilemedi',
                'message' => 'Oranlar şu anda kontrol edilemiyor. Lütfen kısa süre sonra tekrar deneyin.',
                'suggested_action' => 'retry',
            ];
        }

        return [
            'title' => 'Oran güncellendi',
            'message' => 'Seçtiğiniz oran artık geçerli değil. Güncel oranları yeniden yükleyip tekrar deneyebilirsiniz.',
            'suggested_action' => 'refresh_quote',
        ];
    }
}
