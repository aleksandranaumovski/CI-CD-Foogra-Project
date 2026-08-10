<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $paginator = User::query()
            ->withCount(['restaurants', 'reviews', 'bookings'])
            ->when($request->filled('role'), fn (Builder $q) => $q->where('role', $request->query('role')))
            ->when($request->filled('q'), function (Builder $q) use ($request) {
                $term = $request->query('q');
                $q->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%"));
            })
            ->when($request->boolean('trashed'), fn (Builder $q) => $q->onlyTrashed())
            ->orderBy('name')
            ->paginate((int) $request->integer('per_page', 20))
            ->withQueryString();

        return response()->json([
            'data' => UserResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return response()->json([
            'data' => new UserResource($user->loadCount(['restaurants', 'reviews', 'bookings'])),
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create([
            ...$request->validated(),
            'email_verified_at' => now(),
        ]);

        return response()->json([
            'message' => 'User created.',
            'data' => new UserResource($user),
        ], 201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user->update($request->validated());

        return response()->json([
            'message' => 'User updated.',
            'data' => new UserResource($user->fresh()),
        ]);
    }

    /**
     * Soft delete. Guards against an admin locking themselves out, and against
     * orphaning a restaurant that still has an active listing.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        if ($request->user()->id === $user->id) {
            return response()->json(['message' => 'You cannot delete your own account here.'], 409);
        }

        $owned = $user->restaurants()->count();

        if ($owned > 0) {
            return response()->json([
                'message' => "This owner still has {$owned} restaurant(s). Reassign them before deleting the account.",
            ], 409);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted.']);
    }

    public function restore(int $id): JsonResponse
    {
        $user = User::onlyTrashed()->findOrFail($id);

        $this->authorize('delete', $user);

        $user->restore();

        return response()->json([
            'message' => 'User restored.',
            'data' => new UserResource($user),
        ]);
    }
}
