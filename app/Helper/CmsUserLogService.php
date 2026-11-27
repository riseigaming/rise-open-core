<?php

namespace App\Helper;

use App\Enum\UserLogEnum;
use App\Models\CmsUser;
use App\Models\CmsUserLog;
use App\Support\Money\CurrencyPrecision;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class CmsUserLogService
{
    public static function save(CmsUser|Builder $user, CmsUser|Authenticatable|null $operator, string $enum, bool $forceToOperator = false, $extraData = []) : void{
        try {

            $operatorName = trim((string) ($extraData['actorLabel'] ?? ' kendisi '));

            if($operator && ($operator->id != $user->id || $forceToOperator)){
                $operatorName = $operator->full_name." (#$operator->id)";
            } elseif (!$operator) {
                $actorType = trim((string) ($extraData['actorType'] ?? 'system'));
                $operatorName = match ($actorType) {
                    'ai' => 'AI destek',
                    'system' => 'sistem',
                    default => $operatorName !== '' ? $operatorName : 'sistem',
                };
            }

            switch ($enum) {
                case UserLogEnum::LOGGED_IN:
                    $title = "Oturum Açma";
                    $description = $user->full_name . " giriş yaptı";
                    break;
                case UserLogEnum::LOGGED_OUT:
                    $title = "Oturum Sonlandırma";
                    $description = $user->full_name . " oturumu sonlandırdı";
                    break;
                case UserLogEnum::WRONG_LOGIN:
                    $title = "Hatalı Giriş Denemesi";
                    $description = $user->full_name . " hatalı giriş denemesi yaptı.";
                    break;
                case UserLogEnum::PROFILE_EDIT:
                    $title = "Profil Güncelleme";
                    $description = $user->full_name . " kullanıcısının profili " . $operatorName . " tarafından güncellendi";
                    break;
                case UserLogEnum::STATUS_UPDATE:
                    $title = "Durum Güncelleme";
                    $description = $user->full_name . " kullanıcısının durumu ". $operatorName . " tarafından güncellendi";
                    break;
                case UserLogEnum::PASSWORD_EDIT:
                    $title = "Şifre Güncelleme";
                    $description = $user->full_name . " kullanıcısının şifresi ". $operatorName . " tarafından güncellendi";
                    break;
                case UserLogEnum::WITHDRAW:
                    $title = $extraData["logTitle"];
                    $scale = CurrencyPrecision::scaleFor($extraData["currency"] ?? null);
                    $amount = str_replace('.', ',', CurrencyPrecision::format($extraData["amount"] ?? null, $scale));
                    $balance = str_replace('.', ',', CurrencyPrecision::format($extraData["balance"] ?? null, $scale));
                    $description = "Tutar: ".$amount." ".($extraData["currency"] ?? '')." | Bakiye: ".$balance." | Ref TrxId: ".$extraData["refTransactionId"]." | Operatör: ".$operatorName;
                    break;
                case UserLogEnum::ARCHIVED:
                    $title = "Kullanıcı arşive alındı";
                    $description = $user->full_name . " kullanıcısı ". $operatorName ." tarafından arşive alındı";
                    if (!empty($extraData["family_root_id"])) {
                        $description .= " | Aile Kök ID: #".$extraData["family_root_id"];
                    }
                    break;
                case UserLogEnum::RESTORED:
                    $title = "Kullanıcı arşivden çıkarıldı";
                    $description = $user->full_name . " kullanıcısı ". $operatorName ." tarafından arşivden çıkarıldı";
                    if (!empty($extraData["family_root_id"])) {
                        $description .= " | Aile Kök ID: #".$extraData["family_root_id"];
                    }
                    break;
                case UserLogEnum::DISABLED:
                    $title = "Kullanıcı devre dışı bırakıldı";
                    $description = $user->full_name . " kullanıcısının erişimi ". $operatorName ." tarafından durduruldu";
                    if (!empty($extraData["family_root_id"])) {
                        $description .= " | Aile Kök ID: #".$extraData["family_root_id"];
                    }
                    break;
                case UserLogEnum::ENABLED:
                    $title = "Kullanıcı yeniden etkinleştirildi";
                    $description = $user->full_name . " kullanıcısının erişimi ". $operatorName ." tarafından yeniden açıldı";
                    if (!empty($extraData["family_root_id"])) {
                        $description .= " | Aile Kök ID: #".$extraData["family_root_id"];
                    }
                    break;
                default:
                    throw new \InvalidArgumentException('Unsupported B1 user log event.');
            }

            $logData = [
                "cms_user_id" => $user->id,
                "cms_operator_id" => $operator?->id,
                "title" => $title,
                "description" => $description,
                "log_type" => $enum,
            ];

            CmsUserLog::query()->create($logData);

        }catch (\Throwable $th){
            throw new \RuntimeException('CMS user audit could not be persisted.', 0, $th);
        }
    }

    
}
