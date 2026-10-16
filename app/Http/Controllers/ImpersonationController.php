<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ImpersonationController extends Controller
{
    public const SESSION_KEY = 'impersonator_id';

    public function start(Request $request, User $user): RedirectResponse
    {
        $admin = $request->user();
        abort_unless($admin && $this->isAdmin($admin), 403);

        if ($request->session()->has(self::SESSION_KEY)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Leave the current impersonation before starting another.',
            ]);

            return back();
        }

        if ((int) $admin->id === (int) $user->id) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'You cannot impersonate your own account.',
            ]);

            return back();
        }

        $request->session()->put(self::SESSION_KEY, $admin->id);

        Auth::login($user);
        $request->session()->regenerate();

        Log::info('[Impersonation] started', [
            'admin_id' => $admin->id,
            'admin_email' => $admin->email,
            'user_id' => $user->id,
            'user_email' => $user->email,
        ]);

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => 'You are now viewing the app as '.$user->email.'.',
        ]);

        return redirect()->route('dashboard');
    }

    public function leave(Request $request): RedirectResponse
    {
        $adminId = $request->session()->get(self::SESSION_KEY);
        abort_unless($adminId, 403);

        $admin = User::query()->find($adminId);
        abort_unless($admin && $this->isAdmin($admin), 403);

        $impersonated = $request->user();

        $request->session()->forget(self::SESSION_KEY);

        Auth::login($admin);
        $request->session()->regenerate();

        Log::info('[Impersonation] left', [
            'admin_id' => $admin->id,
            'admin_email' => $admin->email,
            'user_id' => $impersonated?->id,
            'user_email' => $impersonated?->email,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Returned to your admin account.',
        ]);

        return redirect()->route('users.index');
    }

    private function isAdmin(User $user): bool
    {
        $email = strtolower((string) $user->email);

        return $email !== '' && collect(config('admin.emails', []))->contains($email);
    }
}
