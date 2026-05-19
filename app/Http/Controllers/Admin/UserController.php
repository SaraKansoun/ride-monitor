<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Driver;
use App\Models\User;
use App\Services\DeactivationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private DeactivationService $deactivationService) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $status = $this->statusFilter($request);
        $role = $this->roleFilter($request);
        $search = $this->searchTerm($request);

        $users = $this->filteredUsers($status, $role, $search)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'role' => $role,
            'roles' => ['all', 'admin', 'monitor', 'driver'],
            'search' => $search,
            'status' => $status,
            'users' => $users,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('admin.users.create', [
            'roles' => ['admin', 'monitor', 'driver'],
            'statuses' => User::STATUSES,
            'user' => new User(['status' => User::STATUS_ACTIVE]),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $actor = $request->user();
            $status = $request->validated('status');
            $user = User::query()->create([
                ...$request->safe()->only(['name', 'email', 'password', 'status']),
                ...$this->deactivationService->attributesForStatus(
                    $status,
                    User::STATUS_ACTIVE,
                    $actor instanceof User ? $actor : null,
                ),
            ]);
            $user->assignRole($request->validated('role'));

            return $user;
        });

        return redirect()->route('admin.users.show', $user)->with('status', 'User created.');
    }

    public function show(User $user): View
    {
        Gate::authorize('view', $user);

        $user->load(['roles', 'driverProfile.currentAssignment.vehicle']);

        return view('admin.users.show', [
            'user' => $user,
        ]);
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        $user->load('roles');

        return view('admin.users.edit', [
            'roles' => ['admin', 'monitor', 'driver'],
            'statuses' => User::STATUSES,
            'user' => $user,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        abort_unless($actor instanceof User, 403);

        if ($request->validated('status') === User::STATUS_INACTIVE) {
            if ($actor->is($user)) {
                return back()->withErrors(['user' => 'You cannot deactivate your own account.']);
            }

            $error = $this->driverDeactivationError($user->driverProfile);

            if ($error !== null) {
                return back()->withErrors(['user' => $error]);
            }
        }

        DB::transaction(function () use ($request, $user): void {
            $data = $request->safe()->only(['name', 'email', 'status']);
            $actor = $request->user();

            if ($request->filled('password')) {
                $data['password'] = $request->validated('password');
            }

            $user->update([
                ...$data,
                ...$this->deactivationService->attributesForStatus(
                    $data['status'],
                    User::STATUS_ACTIVE,
                    $actor instanceof User ? $actor : null,
                ),
            ]);
            $user->syncRoles([$request->validated('role')]);

            $driver = $user->driverProfile;

            if ($driver instanceof Driver) {
                $driver->update([
                    'status' => $data['status'] === User::STATUS_ACTIVE
                        ? Driver::STATUS_ACTIVE
                        : Driver::STATUS_INACTIVE,
                    ...$this->deactivationService->attributesForStatus(
                        $data['status'],
                        User::STATUS_ACTIVE,
                        $actor instanceof User ? $actor : null,
                    ),
                ]);
            }
        });

        return redirect()->route('admin.users.show', $user)->with('status', 'User updated.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('deactivate', $user);

        $actor = $request->user();

        abort_unless($actor instanceof User, 403);

        if ($actor->is($user)) {
            return back()->withErrors(['user' => 'You cannot deactivate your own account.']);
        }

        $user->load('driverProfile');
        $error = $this->driverDeactivationError($user->driverProfile);

        if ($error !== null) {
            return back()->withErrors(['user' => $error]);
        }

        $this->deactivationService->deactivateUser($user, $actor);

        return back()->with('status', 'User deactivated.');
    }

    public function reactivate(User $user): RedirectResponse
    {
        Gate::authorize('reactivate', $user);

        $this->deactivationService->reactivateUser($user);

        return back()->with('status', 'User reactivated.');
    }

    private function statusFilter(Request $request): string
    {
        $status = $request->string('status', User::STATUS_ACTIVE)->toString();

        return in_array($status, [...User::STATUSES, 'all'], true) ? $status : User::STATUS_ACTIVE;
    }

    private function roleFilter(Request $request): string
    {
        $role = $request->string('role', 'all')->toString();

        return in_array($role, ['all', 'admin', 'monitor', 'driver'], true) ? $role : 'all';
    }

    private function searchTerm(Request $request): string
    {
        return trim($request->string('q')->toString());
    }

    /**
     * @return Builder<User>
     */
    private function filteredUsers(string $status, string $role, string $search): Builder
    {
        return User::query()
            ->with(['roles', 'driverProfile.currentAssignment.vehicle'])
            ->when($status === User::STATUS_ACTIVE, fn (Builder $query) => $query->active())
            ->when($status === User::STATUS_INACTIVE, fn (Builder $query) => $query->inactive())
            ->when($role !== 'all', fn (Builder $query) => $query->role($role))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });
    }

    private function driverDeactivationError(mixed $driver): ?string
    {
        if (! $driver instanceof Driver) {
            return null;
        }

        if ($driver->currentAssignment()->exists()) {
            return 'Unassign this driver before deactivating their user account.';
        }

        if ($driver->hasUnresolvedActiveIncidents()) {
            return "Resolve this driver's active incidents before deactivating their user account.";
        }

        return null;
    }
}
