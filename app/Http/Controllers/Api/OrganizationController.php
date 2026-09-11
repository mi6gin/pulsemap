<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Jobs\SyncOrganization;
use App\Models\Organization;
use App\OrganizationStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizationController extends Controller
{
    public function show(Request $request): JsonResponse|OrganizationResource
    {
        $organization = Organization::query()
            ->whereBelongsTo($request->user())
            ->withCount(['reviews as stored_reviews_count'])
            ->with(['snapshots' => fn ($query) => $query->latest('captured_at')->limit(5)])
            ->first();

        return $organization === null
            ? response()->json(['data' => null])
            : new OrganizationResource($organization);
    }

    public function store(StoreOrganizationRequest $request): OrganizationResource
    {
        $sourceUrl = trim($request->string('url')->toString());

        $organization = DB::transaction(function () use ($request, $sourceUrl): Organization {
            $organization = Organization::query()->firstOrNew(['user_id' => $request->user()->id]);
            $sourceChanged = $organization->exists && $organization->source_url !== $sourceUrl;

            if ($sourceChanged) {
                $organization->reviews()->delete();
                $organization->snapshots()->delete();
                $organization->fill([
                    'external_id' => null,
                    'canonical_url' => null,
                    'name' => null,
                    'rating' => null,
                    'ratings_count' => 0,
                    'reviews_count' => 0,
                    'last_synced_at' => null,
                ]);
            }

            $organization->fill([
                'source_url' => $sourceUrl,
                'status' => OrganizationStatus::Queued,
                'progress' => 0,
                'sync_error' => null,
            ])->save();

            return $organization;
        });

        SyncOrganization::dispatch($organization->id, $organization->source_url);

        return new OrganizationResource(
            $organization->loadCount(['reviews as stored_reviews_count'])->load('snapshots'),
        );
    }
}
