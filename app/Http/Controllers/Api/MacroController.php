<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Filters\ApiQuery;
use App\Http\Requests\DestroyManyRequest;
use App\Http\Requests\MacroRequest;
use App\Http\Resources\MacroCollection;
use App\Http\Resources\MacroResource;
use App\Models\Macro;
use App\Models\User;
use App\Services\MacroService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Manage user macros with owner-scoped access and superadmin override.
 */
class MacroController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(private readonly MacroService $macroService)
    {
    }

    /**
     * List macros available to the authenticated user.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return $this->error('Používateľ nie je autentifikovaný', Response::HTTP_UNAUTHORIZED);
        }

        $results = ApiQuery::apply(
            $request,
            $this->macroService->queryForUser($user),
            ['name', 'abbreviation', 'text'],
            ['name', 'abbreviation', 'created_at'],
            ['name' => 'asc']
        );

        return $this->success(new MacroCollection($results), 'Makrá boli načítané');
    }

    /**
     * Create a new macro for the authenticated user.
     */
    public function store(MacroRequest $request)
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return $this->error('Používateľ nie je autentifikovaný', Response::HTTP_UNAUTHORIZED);
        }

        $macro = $this->macroService->createForUser($request->validated(), $user);

        return $this->success(new MacroResource($macro), 'Makro bolo vytvorené', Response::HTTP_CREATED);
    }

    /**
     * Show a single macro when the user is allowed to access it.
     */
    public function show(Request $request, Macro $macro)
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return $this->error('Používateľ nie je autentifikovaný', Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->macroService->canAccess($user, $macro)) {
            return $this->forbidden('Nemáte oprávnenie na prístup k tomuto makru');
        }

        return $this->success(new MacroResource($macro), 'Makro bolo načítané');
    }

    /**
     * Update an existing macro when the user is allowed to access it.
     */
    public function update(MacroRequest $request, Macro $macro)
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return $this->error('Používateľ nie je autentifikovaný', Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->macroService->canAccess($user, $macro)) {
            return $this->forbidden('Nemáte oprávnenie upraviť toto makro');
        }

        $macro = $this->macroService->updateMacro($macro, $request->validated());

        return $this->success(new MacroResource($macro), 'Makro bolo aktualizované');
    }

    /**
     * Delete a macro when the user is allowed to access it.
     */
    public function destroy(Request $request, Macro $macro)
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return $this->error('Používateľ nie je autentifikovaný', Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->macroService->canAccess($user, $macro)) {
            return $this->forbidden('Nemáte oprávnenie odstrániť toto makro');
        }

        $this->macroService->deleteMacro($macro);

        return $this->success(null, 'Makro bolo odstránené', Response::HTTP_NO_CONTENT);
    }

    /**
     * Delete multiple macros accessible to the authenticated user.
     */
    public function destroyMany(DestroyManyRequest $request)
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return $this->error('Používateľ nie je autentifikovaný', Response::HTTP_UNAUTHORIZED);
        }

        $this->macroService->deleteManyForUser($user, $request->validated()['ids']);

        return $this->success(null, 'Makrá boli odstránené');
    }
}
