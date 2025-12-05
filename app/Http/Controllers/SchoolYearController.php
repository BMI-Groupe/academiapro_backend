<?php

namespace App\Http\Controllers;

use App\Http\Requests\SchoolYearStoreRequest;
use App\Http\Requests\SchoolYearUpdateRequest;
use App\Http\Resources\SchoolYearResource;
use App\Models\SchoolYear;
use App\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchoolYearController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->query('per_page');
        $query = SchoolYear::query()->orderByDesc('year_start');
        $data = $perPage ? $query->paginate($perPage) : $query->get();

        // If paginated, wrap in resource collection
        if ($data instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $data = $data->through(fn ($s) => new SchoolYearResource($s));
            return ApiResponse::sendResponse(true, [$data], 'Opération effectuée.', 200);
        }

        return ApiResponse::sendResponse(true, [\App\Http\Resources\SchoolYearResource::collection($data)], 'Opération effectuée.', 200);
    }

    public function active()
    {
        $year = SchoolYear::active();
        if (!$year) {
            return ApiResponse::sendResponse(false, [], 'Aucune année scolaire active trouvée.', 404);
        }

        return ApiResponse::sendResponse(true, [new SchoolYearResource($year)], 'Opération effectuée.', 200);
    }

    public function store(SchoolYearStoreRequest $request)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            
            // If this year is being set as active, deactivate all other years
            if (isset($data['is_active']) && $data['is_active']) {
                SchoolYear::where('is_active', true)->update(['is_active' => false]);
            }
            
            $year = SchoolYear::create($data);
            DB::commit();
            return ApiResponse::sendResponse(true, [new SchoolYearResource($year)], 'Année scolaire créée.', 201);
        } catch (\Throwable $th) {
            return ApiResponse::rollback($th);
        }
    }

    public function update(SchoolYearUpdateRequest $request, SchoolYear $schoolYear)
    {
        DB::beginTransaction();
        try {
            $data = $request->validated();
            
            // If this year is being set as active, deactivate all other years
            if (isset($data['is_active']) && $data['is_active']) {
                SchoolYear::where('id', '!=', $schoolYear->id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }
            
            $schoolYear->update($data);
            DB::commit();
            return ApiResponse::sendResponse(true, [new SchoolYearResource($schoolYear)], 'Année scolaire mise à jour.', 200);
        } catch (\Throwable $th) {
            return ApiResponse::rollback($th);
        }
    }

    public function destroy(SchoolYear $schoolYear)
    {
        DB::beginTransaction();
        try {
            $schoolYear->delete();
            DB::commit();
            return ApiResponse::sendResponse(true, [], 'Année scolaire supprimée.', 200);
        } catch (\Throwable $th) {
            return ApiResponse::rollback($th);
        }
    }
}
