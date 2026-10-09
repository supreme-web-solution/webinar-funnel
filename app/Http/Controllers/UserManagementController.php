<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Auth\UserRoleAssigner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function __construct(
        private readonly UserRoleAssigner $roles,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);

        $search = trim((string) $request->query('search', ''));

        $query = User::query()
            ->with(['roles:id,name', 'reseller:id,email'])
            ->select(['id', 'uuid', 'reseller_id', 'name', 'username', 'email', 'email_verified_at', 'created_at'])
            ->latest();

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate(20)->withQueryString();
        $users->getCollection()->transform(fn (User $user): array => $this->userRow($user));

        return Inertia::render('users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
            ],
            'stats' => [
                'total' => User::query()->count(),
                'verified' => User::query()->whereNotNull('email_verified_at')->count(),
            ],
            'adminEmails' => $this->adminEmails(),
            'rolesEnabled' => $this->roles->rolesEnabled(),
            'assignableRoles' => $this->roles->assignableRoles(),
            'defaultRole' => UserRoleAssigner::DEFAULT_ROLE,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:80', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            ...$this->roleRules(),
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $this->roles->syncRoles($user, $validated['roles'] ?? [UserRoleAssigner::DEFAULT_ROLE]);

        return back()->with('success', 'User created successfully.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:80', 'alpha_dash', 'unique:users,username,'.$user->id],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            ...$this->roleRules(),
        ]);

        $updates = [
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
        ];

        if (! empty($validated['password'])) {
            $updates['password'] = Hash::make($validated['password']);
        }

        $user->update($updates);

        if (array_key_exists('roles', $validated)) {
            $this->roles->syncRoles($user, $validated['roles'] ?? []);
        }

        return back()->with('success', ! empty($validated['password'])
            ? 'User updated and password changed.'
            : 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request);

        if ((int) $request->user()->id === (int) $user->id) {
            return back()->withErrors([
                'delete' => 'You cannot delete your own admin account.',
            ]);
        }

        if ($this->adminEmails()->contains(strtolower((string) $user->email))) {
            return back()->withErrors([
                'delete' => 'Admin users listed in ADMIN_EMAILS cannot be deleted.',
            ]);
        }

        $user->delete();

        return back()->with('success', 'User deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function userRow(User $user): array
    {
        return [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'created_at' => $user->created_at,
            'roles' => $user->roles->pluck('name')->values()->all(),
            'reseller_email' => $user->reseller?->email,
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function roleRules(): array
    {
        if (! $this->roles->rolesEnabled()) {
            return [
                'roles' => ['nullable', 'array'],
                'roles.*' => ['string'],
            ];
        }

        return [
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::in($this->roles->assignableRoles())],
        ];
    }

    private function authorizeAdmin(Request $request): void
    {
        $email = strtolower((string) optional($request->user())->email);
        $allowed = $this->adminEmails();

        abort_unless($email !== '' && $allowed->contains($email), 403);
    }

    /**
     * @return Collection<int, string>
     */
    private function adminEmails()
    {
        return collect(config('admin.emails', []));
    }
}
