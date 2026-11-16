<?php

namespace App\Http\Controllers\Staff;

use App\Enums\AuthEventType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreStaffRequest;
use App\Http\Requests\Staff\UpdateStaffRequest;
use App\Http\Resources\StaffResource;
use App\Models\User;
use App\Services\AuthEventRecorder;
use App\Services\StaffAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class StaffController extends Controller
{
    use ResolvesActor;

    public function __construct(
        private readonly StaffAccountService $accounts,
        private readonly AuthEventRecorder $events,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::query()
            ->where('type', UserType::Staff->value)
            ->orderBy('name');

        $status = UserStatus::tryFrom((string) $request->query('status'));
        if ($status !== null) {
            $query->where('status', $status->value);
        }

        $role = UserRole::tryFrom((string) $request->query('role'));
        if ($role !== null) {
            $query->where('role', $role->value);
        }

        return StaffResource::collection($query->paginate(20)->withQueryString());
    }

    public function show(User $user): StaffResource
    {
        $this->accounts->ensureStaff($user);

        return new StaffResource($user);
    }

    public function store(StoreStaffRequest $request): JsonResponse
    {
        $user = $this->accounts->create($request->validated());

        $this->events->record(AuthEventType::StaffCreated, $request, $this->actor($request), $user);

        return (new StaffResource($user))->response()->setStatusCode(201);
    }

    public function update(UpdateStaffRequest $request, User $user): StaffResource
    {
        $updated = $this->accounts->update($this->actor($request), $user, $request->validated());

        $this->events->record(AuthEventType::StaffUpdated, $request, $this->actor($request), $updated);

        return new StaffResource($updated);
    }

    public function destroy(Request $request, User $user): Response
    {
        $this->accounts->delete($this->actor($request), $user);

        $this->events->record(AuthEventType::StaffDeleted, $request, $this->actor($request), $user);

        return response()->noContent();
    }
}
