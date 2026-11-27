<?php
namespace App\Services\Games;

use App\Models\CmsSensitiveMutationEvent;
use Illuminate\Database\Eloquent\Model;

final class CatalogAudit
{
    /** Existing B1 audit storage; intentionally excludes credentials and submitted form values. */
    public static function record(string $event, Model $subject, array $metadata = []): void
    {
        CmsSensitiveMutationEvent::create([
            'event_type'=>$event,'subject_type'=>get_class($subject),'subject_id'=>$subject->getKey(),
            'actor_type'=>\App\Models\CmsUser::class,'actor_id'=>auth('cms')->id(),
            'source'=>'base_catalog_admin','route_name'=>request()->route()?->getName(),
            'request_path'=>request()->path(),'metadata'=>$metadata,
        ]);
    }
}
