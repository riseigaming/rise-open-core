<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmsUserType extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_NORMAL = 'Normal';
    public const TYPE_VIP = 'VIP';
    public const TYPE_RISKY = 'Riskli';

    protected $guarded = ["id"];
    protected $fillable = [
        "name",
        "description",
    ];

    public static function coreDefinitions(): array
    {
        return [
            self::TYPE_NORMAL => 'Varsayılan kullanıcı etiketi. Admin özel bir sınıflandırma yapmadıysa kullanıcı burada kalır.',
            self::TYPE_VIP => 'Özel müşteri takibi, öncelikli destek ve ayrı operasyonel yüzeyler için hazır VIP etiketi.',
            self::TYPE_RISKY => 'Risk/fraud/uyumluluk dikkat gerektiren kullanıcıları görünür işaretlemek için hazır etiket.',
        ];
    }

    public static function ensureCoreCatalog(): self
    {
        foreach (self::coreDefinitions() as $name => $description) {
            $type = self::withTrashed()->firstOrNew(['name' => $name]);
            $type->description = $description;

            if ($type->trashed()) {
                $type->restore();
            }

            $type->save();
        }

        return self::query()->firstWhere('name', self::TYPE_NORMAL);
    }

    public static function orderedForSelection()
    {
        self::ensureCoreCatalog();

        return self::query()
            ->orderByRaw("
                CASE name
                    WHEN ? THEN 0
                    WHEN ? THEN 1
                    WHEN ? THEN 2
                    ELSE 3
                END
            ", [self::TYPE_NORMAL, self::TYPE_VIP, self::TYPE_RISKY])
            ->orderBy('name')
            ->get();
    }

    public static function defaultTypeId(): ?int
    {
        return optional(self::ensureCoreCatalog())->id;
    }

}
