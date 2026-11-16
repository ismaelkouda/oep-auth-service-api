<?php

namespace App\Http\Controllers\Staff;

use App\Enums\AuthEventType;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Http\Resources\StaffResource;
use App\Models\User;
use App\Services\AuthEventRecorder;
use App\Services\StaffAccountService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StaffAccountController extends Controller
{
    use ResolvesActor;

    public function __construct(
        private readonly StaffAccountService $accounts,
        private readonly AuthEventRecorder $events,
    ) {}

    public function suspend(Request $request, User $user): StaffResource
    {
        $this->accounts->suspend($this->actor($request), $user);

        $this->events->record(AuthEventType::StaffSuspended, $request, $this->actor($request), $user);

        return new StaffResource($user->refresh());
    }

    public function reactivate(Request $request, User $user): StaffResource
    {
        $this->accounts->reactivate($user);

        $this->events->record(AuthEventType::StaffReactivated, $request, $this->actor($request), $user);

        return new StaffResource($user->refresh());
    }

    public function unlock(Request $request, User $user): StaffResource
    {
        $this->accounts->unlock($user);

        $this->events->record(AuthEventType::StaffUnlocked, $request, $this->actor($request), $user);

        return new StaffResource($user->refresh());
    }

    public function invite(Request $request, User $user): Response
    {
        $this->accounts->resendInvitation($user);

        $this->events->record(AuthEventType::StaffInvited, $request, $this->actor($request), $user);

        return response()->noContent();
    }
}
