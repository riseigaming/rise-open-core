<?php

namespace App\Http\Controllers\Cms\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsLanguage;
use App\Services\Localization\CountryDefaultLanguageService;
use App\Services\Localization\CmsLanguageMutationService;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Illuminate\Validation\ValidationException;
use Throwable;

class LanguageController extends Controller
{
    public function index(CountryDefaultLanguageService $countryDefaults){
        return view("cms.admin.languages.index", [
            'countryDefaults' => $countryDefaults->summary(),
        ]);
    }

    public function getAjaxList(Request $request) {
        $sort = $request->get('sort', 'id');
        abort_unless(in_array($sort, ['id','name','code','is_default','status'], true), 422);
        $query = CmsLanguage::when($request->search, function ($q) use ($request) {
            $search = '%'.implode('%', explode(' ', $request->search)).'%';
            $q->where(fn ($q) => $q->where('name', 'like', $search)->orWhere('code', 'like', $search));
        });
        $total = (clone $query)->count();
        $languages = $query->orderBy($sort, strtolower((string) $request->get('order', 'desc')) === 'asc' ? 'asc' : 'desc')
            ->offset(max(0, (int) $request->get('offset', 0)))
            ->limit(min(250, max(1, (int) $request->get('limit', 10))))->get();
        return response()->json(['rows' => $languages, 'total' => $total, 'count' => $total]);
    }

    public function create(){
        return view("cms.admin.languages.create");
    }

    public function edit($id){

        $language = CmsLanguage::query()->find($id);
        $data = [
            "language" =>$language
        ];
        return view("cms.admin.languages.edit", $data);
    }
    public function store(Request $request){
        try {
            $requestData = $request->only((new CmsLanguage())->getFillable());
            $store = app(CmsLanguageMutationService::class)->save($requestData);

            if($store){
                return response()->json([
                    "status" => 200,
                    "message" => __('admin.copy.record_added')
                ]);
            }

            return response()->json([
                "status" => 200,
                "message" => __('admin.copy.generic_error')
            ]);
        } catch (ValidationException $e) {
            return $this->invalidMutation($e);
        } catch (Throwable $e){
            return response()->json([
                "status" => 500,
                "message" => "Server Error"
            ]);
        }
    }

    public function bulkAction(Request $request){
        $ids = explode(",", $request->get("ids",""));

        if(count($ids) <= 0 ){
            return response()->json([
                "status" => 400,
                "message" => __('admin.copy.selection_required')
            ]);
        }
        try {
            app(CmsLanguageMutationService::class)->bulk($ids, (string) $request->get('action'));
        } catch (ValidationException $e) {
            return $this->invalidMutation($e);
        }

        return response()->json([
            "status" => 201,
            "message" => __('admin.copy.action_success')
        ]);
    }

    public function delete($id){
        try {
            app(CmsLanguageMutationService::class)->bulk([$id], 'delete');
            return response()->json([
                "status" => 200,
                "message" => __('admin.copy.record_deleted')
            ]);
        } catch (ValidationException $e) {
            return $this->invalidMutation($e);
        }
    }

    public function update($id, Request $request){
        $requestData = $request->only((new CmsLanguage())->getFillable());
        try {
            $update = app(CmsLanguageMutationService::class)->save($requestData, (int) $id);
        } catch (ValidationException $e) {
            return $this->invalidMutation($e);
        }

        if($update){
            return response()->json([
                "status" => 200,
                "message" => __('admin.copy.record_updated')
            ]);
        }
        return response()->json([
            "status" => 500,
            "message" => __('admin.copy.generic_error')
        ]);
    }

    private function invalidMutation(ValidationException $exception)
    {
        return response()->json([
            'status' => 422,
            'message' => collect($exception->errors())->flatten()->first(),
            'errors' => $exception->errors(),
        ], 422);
    }

    public function setCountryDefault(Request $request, CountryDefaultLanguageService $countryDefaults)
    {
        $validated = $request->validate([
            'country_id' => ['required', 'integer', 'min:1'],
            'language_code' => ['required', 'string', 'max:5'],
        ]);

        try {
            $countryDefaults->setCountryDefault(
                (int) $validated['country_id'],
                (string) $validated['language_code'],
                $request->user('cms')?->id
            );

            return response()->json([
                'status' => 200,
                'message' => __('admin.language_country_default.saved'),
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'status' => 422,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable) {
            return response()->json([
                'status' => 500,
                'message' => __('admin.language_country_default.save_failed'),
            ], 500);
        }
    }

    public function removeCountryDefault(int $countryId, CountryDefaultLanguageService $countryDefaults)
    {
        try {
            $countryDefaults->removeCountryDefault($countryId);

            return response()->json([
                'status' => 200,
                'message' => __('admin.language_country_default.removed'),
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'status' => 422,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable) {
            return response()->json([
                'status' => 500,
                'message' => __('admin.language_country_default.remove_failed'),
            ], 500);
        }
    }

    
}
