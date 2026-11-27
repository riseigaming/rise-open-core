<?php

namespace App\Services\Localization;

use App\Models\CmsLanguage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CmsLanguageMutationService
{
    public function save(array $data, ?int $id = null): CmsLanguage
    {
        $data = Validator::make($data, [
            'name' => [$id === null ? 'required' : 'sometimes', 'string', 'max:30'],
            'code' => [$id === null ? 'required' : 'sometimes', 'string', 'max:5'],
            'status' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
        ])->validate();

        return DB::transaction(function () use ($data, $id) {
            // Every supported mutation takes this deterministic lock first.
            $languages = CmsLanguage::query()->orderBy('id')->lockForUpdate()->get();
            $isFirstCreate = $id === null && $languages->isEmpty();
            $language = $id === null
                ? new CmsLanguage(['status' => 1, 'is_default' => 0])
                : $languages->firstWhere('id', $id);
            if (!$language) {
                throw ValidationException::withMessages(['id' => __('language_management.not_found')]);
            }

            $wasDefault = (bool) $language->is_default;
            $hadActive = $languages->contains(fn (CmsLanguage $row) => (bool) $row->status);
            $language->fill($data);
            if ($wasDefault && array_key_exists('is_default', $data) && !(bool) $language->is_default) {
                $this->rejectDefaultRemoval();
            }
            if (array_key_exists('is_default', $data) && (bool) $language->is_default && !$language->status) {
                throw ValidationException::withMessages(['is_default' => __('language_management.active_required')]);
            }

            if ($isFirstCreate) {
                // A CMS locale table becomes durable only once it has an active
                // default; an inactive first row would create an invalid state.
                $language->setAttribute('status', 1);
                $language->setAttribute('is_default', 1);
            } elseif ($hadActive
                && $this->activeAfterSave($languages, $language)->isEmpty()) {
                throw ValidationException::withMessages(['status' => __('language_management.last_active_required')]);
            }

            // Free the unique active-default slot before selecting a new row.
            // Both changes stay under the same locked transaction.
            if ($language->status && $language->is_default) {
                CmsLanguage::query()->when($language->exists, fn ($query) => $query->whereKeyNot($language->id))
                    ->where('is_default', 1)->update(['is_default' => 0]);
            }
            $language->save();
            $this->repairDefaultsLocked(array_key_exists('is_default', $data) && (bool) $data['is_default'] ? $language->id : null);

            return $language->fresh();
        });
    }

    public function bulk(array $ids, string $action): void
    {
        Validator::make(['ids' => $ids, 'action' => $action], [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'min:1'],
            'action' => ['required', 'in:active,passive,delete'],
        ])->validate();

        DB::transaction(function () use ($ids, $action) {
            $languages = CmsLanguage::query()->orderBy('id')->lockForUpdate()->get();
            $selected = $languages->whereIn('id', $ids);
            if ($selected->count() !== count(array_unique($ids))) {
                throw ValidationException::withMessages(['ids' => __('language_management.not_found')]);
            }

            $hadActive = $languages->contains(fn (CmsLanguage $language) => (bool) $language->status);
            $remaining = match ($action) {
                'delete' => $languages->reject(fn (CmsLanguage $language) => $selected->contains('id', $language->id)),
                'passive' => $languages->map(function (CmsLanguage $language) use ($selected): CmsLanguage {
                    if ($selected->contains('id', $language->id)) {
                        $language->setAttribute('status', 0);
                    }

                    return $language;
                }),
                default => $languages,
            };
            if (($action === 'delete' && $remaining->isEmpty())
                || ($hadActive && $remaining->filter(fn (CmsLanguage $language) => (bool) $language->status)->isEmpty())) {
                throw ValidationException::withMessages(['status' => __('language_management.last_active_required')]);
            }

            $query = CmsLanguage::query()->whereIn('id', $selected->modelKeys());
            if ($action === 'delete') {
                $query->delete();
            } elseif ($action === 'active') {
                // A corrupt inactive default must not jump ahead of the selected
                // deterministic default merely by being activated.
                $query->update(['status' => 1, 'is_default' => 0]);
            } else {
                $query->update(['status' => 0]);
            }

            $this->repairDefaultsLocked();
        });
    }

    private function activeAfterSave(Collection $languages, CmsLanguage $candidate): Collection
    {
        return $languages
            ->map(fn (CmsLanguage $row) => (int) $row->id === (int) $candidate->id ? $candidate : $row)
            ->filter(fn (CmsLanguage $row) => (bool) $row->status);
    }

    private function repairDefaultsLocked(?int $preferredId = null): void
    {
        $all = CmsLanguage::query()->orderBy('id')->lockForUpdate()->get();
        if ($all->isEmpty()) {
            return;
        }

        $active = $all->filter(fn (CmsLanguage $language) => (bool) $language->status);
        if ($active->isEmpty()) {
            // Repairs a legacy zero-active state only. Supported disable/delete
            // mutations are rejected earlier instead of silently succeeding.
            $replacement = $this->configuredOrFirst($all);
            CmsLanguage::query()->whereKey($replacement->id)->update(['status' => 1]);
            $all = CmsLanguage::query()->orderBy('id')->lockForUpdate()->get();
            $active = $all->filter(fn (CmsLanguage $language) => (bool) $language->status);
        }

        $chosen = $preferredId ? $active->firstWhere('id', $preferredId) : null;
        if (!$chosen) {
            $existingDefaults = $active->filter(fn (CmsLanguage $language) => (bool) $language->is_default);
            // Preserve one healthy default. Corrupt zero/multiple states instead
            // converge to the configured active fallback, then lowest ID.
            $chosen = $existingDefaults->count() === 1
                ? $existingDefaults->first()
                : $this->configuredOrFirst($active);
        }

        CmsLanguage::query()->where('is_default', 1)->update(['is_default' => 0]);
        CmsLanguage::query()->whereKey($chosen->id)->update(['is_default' => 1]);
    }

    private function configuredOrFirst(Collection $languages): CmsLanguage
    {
        $configured = strtolower((string) config('app.fallback_locale', config('app.locale', 'en')));

        return $languages->first(fn (CmsLanguage $language) => strtolower((string) $language->code) === $configured)
            ?: $languages->first();
    }

    private function rejectDefaultRemoval(): never
    {
        throw ValidationException::withMessages(['is_default' => __('language_management.choose_default_first')]);
    }
}
