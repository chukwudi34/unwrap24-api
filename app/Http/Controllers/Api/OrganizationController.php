<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use App\Models\OrganizationMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationController extends Controller
{
    /**
     * The "organization setup" step a new Business-plan creator passes
     * through on their way to checkout. Kept intentionally minimal --
     * just enough (a named organization the user owns) for the Business
     * plan's checkout and subscription to attach to.
     */
    public function store(CreateOrganizationRequest $request): JsonResponse
    {
        $user = $request->user();
        $slug = $request->validated('slug') ?: Str::slug($request->validated('name'));
        $slug = $this->uniqueSlug($slug);

        $organization = DB::transaction(function () use ($user, $request, $slug) {
            $organization = Organization::create([
                'name' => $request->validated('name'),
                'slug' => $slug,
                'owner_id' => $user->id,
            ]);

            OrganizationMember::create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'role' => 'owner',
            ]);

            return $organization;
        });

        return response()->json(['data' => new OrganizationResource($organization)], 201);
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $suffix = 1;

        while (Organization::where('slug', $slug)->exists()) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
