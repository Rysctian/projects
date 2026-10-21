<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);

        return Inertia::render('admin/Users', ['users' => User::orderBy('name')->paginate(30)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users,email']);
        $user = DB::transaction(function () use ($request, $data) {
            $user = User::create([...$data, 'password' => Str::random(64)]);
            $user->forceFill(['password_setup_required' => true])->save();
            AuditLog::record($request->user(), $user, 'user.created', after: $user->only(['name', 'email', 'is_active', 'is_system_admin']));

            return $user;
        });

        return $this->sendInvitation($request, $user);
    }

    public function invitation(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request);
        abort_unless($user->is_active && $user->password_setup_required, 422, 'Only active accounts awaiting initial setup can be invited.');

        return $this->sendInvitation($request, $user);
    }

    private function sendInvitation(Request $request, User $user): RedirectResponse
    {
        try {
            $status = app(AccountService::class)->sendInvitation($request->user(), $user);
        } catch (\Throwable $error) {
            report($error);

            return back()->withErrors(['invitation' => 'The account exists, but mail delivery failed. Check the mail transport and resend the setup link after one minute.']);
        }
        if ($status !== Password::RESET_LINK_SENT) {
            return back()->withErrors(['invitation' => __($status)]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Password setup link sent.']);

        return back();
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['is_active' => 'required|boolean', 'is_system_admin' => 'required|boolean']);
        app(AccountService::class)->updateFlags($request->user(), $user, $data);

        return back();
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->hasGlobalPermission('users.manage'), 403);
    }
}
