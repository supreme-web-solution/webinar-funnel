<?php

namespace App\Http\Controllers;

use App\Mail\WelcomeMail;
use App\Models\User;
use App\Services\Jvzoo\JvzooUserProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ResellerController extends Controller
{
    public const ACCOUNT_ROLE = 'FE';

    public function __construct(
        private readonly JvzooUserProvisioner $provisioner,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $query = $request->user()
            ->resellerAccounts()
            ->select(['id', 'reseller_id', 'name', 'username', 'email', 'created_at'])
            ->latest();

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $accounts = $query->paginate(20)->withQueryString();
        $accounts->getCollection()->transform(fn (User $account): array => [
            'id' => $account->id,
            'name' => $account->name,
            'username' => $account->username,
            'email' => $account->email,
            'created_at' => $account->created_at,
        ]);

        return Inertia::render('reseller/Index', [
            'accounts' => $accounts,
            'filters' => [
                'search' => $search,
            ],
            'stats' => [
                'total' => $request->user()->resellerAccounts()->count(),
            ],
            'accountRole' => self::ACCOUNT_ROLE,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
        ]);

        $result = $this->provisioner->createUser(
            email: $validated['email'],
            roleName: self::ACCOUNT_ROLE,
            name: $validated['name'],
            reseller: $request->user(),
        );

        if ($this->sendWelcomeEmail($result['user'], $result['password'])) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => "Account created. Login details were emailed to {$result['user']->email}.",
            ]);
        } else {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => 'Account created, but the welcome email could not be sent. Share the login details below with your customer.',
            ]);
            Inertia::flash('resellerCredentials', [
                'email' => $result['user']->email,
                'password' => $result['password'],
            ]);
        }

        return back();
    }

    public function destroy(Request $request, User $account): RedirectResponse
    {
        abort_unless((int) $account->reseller_id === (int) $request->user()->id, 404);

        $account->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Account deleted.']);

        return back();
    }

    private function sendWelcomeEmail(User $user, string $password): bool
    {
        try {
            Mail::to($user->email)->send(new WelcomeMail($user, $password));

            return true;
        } catch (Throwable $exception) {
            Log::error('Reseller welcome email failed to send.', [
                'user_id' => $user->id,
                'reseller_id' => $user->reseller_id,
                'exception' => $exception->getMessage(),
            ]);

            report($exception);

            return false;
        }
    }
}
