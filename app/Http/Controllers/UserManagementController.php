<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);

        $search = trim((string) $request->query('search', ''));

        $query = User::query()
            ->select(['id', 'uuid', 'name', 'username', 'email', 'email_verified_at', 'created_at'])
            ->latest();

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        return Inertia::render('users/Index', [
            'users' => $query->paginate(20)->withQueryString(),
            'filters' => [
                'search' => $search,
            ],
            'stats' => [
                'total' => User::query()->count(),
                'verified' => User::query()->whereNotNull('email_verified_at')->count(),
            ],
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:80', 'alpha_dash', 'unique:users,username,'.$user->id],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
        ]);

        $user->update($validated);

        return back()->with('success', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request);

        if ((int) $request->user()->id === (int) $user->id) {
            return back()->withErrors([
                'delete' => 'You cannot delete your own admin account.',
            ]);
        }

        $user->delete();

        return back()->with('success', 'User deleted.');
    }

    private function authorizeAdmin(Request $request): void
    {
        $email = strtolower((string) optional($request->user())->email);
        $allowed = collect(explode(',', (string) env('ADMIN_EMAILS', '')))
            ->map(fn ($item) => strtolower(trim($item)))
            ->filter()
            ->values();

        abort_unless($email !== '' && $allowed->contains($email), 403);
    }
}
