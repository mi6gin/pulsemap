<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationResource;
use App\Jobs\SyncOrganization;
use App\Models\Organization;
use App\OrganizationStatus;
use Illuminate\Http\Request;

class OrganizationSyncController extends Controller
{
    public function __invoke(Request $request): OrganizationResource
    {
        $organization = Organization::query()
            ->whereBelongsTo($request->user())
            ->firstOrFail();

        $organization->update([
            'status' => OrganizationStatus::Queued,
            'progress' => 0,
            'sync_error' => null,
        ]);

        SyncOrganization::dispatch($organization->id);

        return new OrganizationResource(
            $organization->loadCount(['reviews as stored_reviews_count'])->load('snapshots'),
        );
    }
}
