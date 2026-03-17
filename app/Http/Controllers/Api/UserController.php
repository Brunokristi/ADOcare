<?php

namespace App\Http\Controllers\Api;

use App\Http\Filters\ApiQuery;
use App\Http\Resources\UserCollection;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index(Request $request)
    {
        $query = User::query();
        $results = ApiQuery::apply(request(), $query);
        return $this->success(new UserCollection($results), 'Users retrieved');
    }

    public function store(\App\Http\Requests\StoreUserRequest $request)
    {
        $data = $request->all();
        $item = $this->userService->create($data);
        return $this->success($item, 'Created', Response::HTTP_CREATED);
    }

    public function show(User $user)
    {
        // make sure role_id is present in JSON (it should be unless hidden)
        return $this->success($user->load(['branches', 'role', 'company']), 'User retrieved');
    }

    public function update(\App\Http\Requests\UpdateUserRequest $request, User $user)
    {
        $data = $request->all();
        $updated = $this->userService->update($user, $data);
        return $this->success($updated, 'Updated');
    }

    public function destroy(User $user)
    {
        $user->delete();
        return $this->success(null, 'Deleted', Response::HTTP_NO_CONTENT);
    }

    public function destroyMany(Request $request)
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return response()->json(['message' => 'No IDs provided'], 400);
        }

        User::whereIn('id', $ids)->delete();

        return $this->success(null, 'Users deleted successfully');
    }

    public function deleteBranchAssignment(User $user, $branch)
    {
        $user->branches()->detach($branch);
        return $this->success(null, 'Branch assignment deleted', Response::HTTP_NO_CONTENT);
    }

    /**
     * Upload or replace user signature image.
     */
    public function uploadSignature(Request $request, User $user)
    {
        $data = $request->validate([
            'signature' => 'required|file|mimes:png|max:5120',
        ]);

        if ($user->signature_path) {
            Storage::disk('local')->delete($user->signature_path);
        }

        $path = $data['signature']->store('signatures/users', 'local');
        $user->update(['signature_path' => $path]);

        return $this->success($user->fresh(), 'Signature uploaded');
    }

    /**
     * Stream user signature image.
     */
    public function signature(User $user)
    {
        if (!$user->signature_path || !Storage::disk('local')->exists($user->signature_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($user->signature_path, null, ['Content-Type' => 'image/png']);
    }

    /**
     * Delete user signature image.
     */
    public function deleteSignature(User $user)
    {
        if ($user->signature_path) {
            Storage::disk('local')->delete($user->signature_path);
            $user->update(['signature_path' => null]);
        }

        return $this->success(null, 'Signature deleted', Response::HTTP_NO_CONTENT);
    }
}