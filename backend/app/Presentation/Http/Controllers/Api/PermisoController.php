<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api;

use App\Application\Auth\Services\AuthorizationService;
use App\Application\Permiso\UseCases\GetPermisosUseCase;
use App\Application\Permiso\UseCases\SavePermisoUseCase;
use App\Domain\Departamento\Repositories\DepartamentoRepositoryInterface;
use App\Domain\Permiso\Repositories\PermisoRepositoryInterface;
use App\Domain\User\Entities\User as DomainUser;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Presentation\Http\Requests\Permiso\SavePermisosRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermisoController extends Controller
{
    public function __construct(
        private readonly GetPermisosUseCase $getPermisosUseCase,
        private readonly SavePermisoUseCase $savePermisoUseCase,
        private readonly AuthorizationService $authorizationService,
        private readonly PermisoRepositoryInterface $permisoRepository,
        private readonly DepartamentoRepositoryInterface $departamentoRepository,
    ) {}

    /**
     * Obtiene los permisos de un usuario específico.
     */
    public function show(Request $request, int $userId): JsonResponse
    {
        if ($request->user()->id !== $userId && $request->user()->rol !== 'ADMIN') {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $permisos = $this->getPermisosUseCase->execute($userId);
        return response()->json($permisos->map(fn($p) => $p->toArray()));
    }

    /**
     * Obtiene los permisos del usuario autenticado.
     */
    public function myPermissions(Request $request): JsonResponse
    {
        $permisos = $this->getPermisosUseCase->execute($request->user()->id);
        return response()->json($permisos->map(fn($p) => $p->toArray()));
    }

    /**
     * Unified permissions endpoint for the frontend.
     * Returns global_role, permissions[], and departments[].
     */
    public function userPermissions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $domainUser = $this->toDomainUser($user);
        $userId = $user->id;

        // Global role
        $globalRole = $domainUser->rol ?? 'USER';

        // Module-level permissions
        $permisos = $this->permisoRepository->findByUserId($userId);
        $permissions = $permisos->map(fn($p) => $p->toArray())->values();

        // Department roles
        $departments = $this->departamentoRepository->findAllByUserId($userId);
        $departmentRoles = $departments->map(function ($dept) use ($domainUser, $userId) {
            $deptRole = $this->authorizationService->getDepartmentRole($domainUser, $dept->id);
            return [
                'id' => $dept->id,
                'nombre' => $dept->nombre,
                'role' => $deptRole,
            ];
        })->values();

        return response()->json([
            'global_role' => $globalRole,
            'permissions' => $permissions,
            'departments' => $departmentRoles,
        ]);
    }

    private function toDomainUser(User $user): DomainUser
    {
        return new DomainUser(
            id: $user->id,
            name: $user->name ?? '',
            email: $user->email ?? '',
            rol: $user->rol,
        );
    }

    /**
     * Guarda los permisos de un usuario específico (reemplaza todos).
     */
    public function save(int $userId, SavePermisosRequest $request): JsonResponse
    {
        $results = $this->savePermisoUseCase->saveAll($userId, $request->validated()['permisos']);
        return response()->json([
            'message' => 'Permisos guardados correctamente.',
            'permisos' => array_map(fn($r) => $r->toArray(), $results),
        ]);
    }
}
