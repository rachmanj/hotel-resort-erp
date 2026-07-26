<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Enums\SpaAppointmentStatus;
use App\Http\Requests\StoreSpaAppointmentRequest;
use App\Http\Requests\UpdateSpaAppointmentStatusRequest;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\SpaAppointment;
use App\Models\SpaTherapist;
use App\Models\SpaTreatment;
use App\Services\SpaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SpaAppointmentController extends Controller
{
    public function __construct(
        private SpaService $spaService,
    ) {}

    public function index(Request $request): Response
    {
        $appointments = SpaAppointment::query()
            ->with(['treatment:id,name,duration_minutes,price', 'therapist:id,name', 'guest:id,full_name', 'reservation:id,reservation_code'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('scheduled_at', $request->string('date')))
            ->when($request->filled('therapist_id'), fn ($q) => $q->where('spa_therapist_id', $request->integer('therapist_id')))
            ->orderBy('scheduled_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SpaAppointment $appointment) => $this->formatAppointment($appointment));

        return Inertia::render('Spa/Appointments/Index', [
            'appointments' => $appointments,
            'filters' => $request->only(['status', 'date', 'therapist_id']),
            'statusOptions' => collect(SpaAppointmentStatus::cases())->map(fn (SpaAppointmentStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'treatments' => SpaTreatment::query()->orderBy('name')->get(['id', 'name', 'duration_minutes', 'price']),
            'therapists' => SpaTherapist::query()->orderBy('name')->get(['id', 'name']),
            'guests' => Guest::query()->orderBy('full_name')->limit(100)->get(['id', 'full_name']),
            'checkedInReservations' => Reservation::query()
                ->with('guest:id,full_name')
                ->where('status', ReservationStatus::CheckedIn->value)
                ->orderByDesc('arrival_date')
                ->get()
                ->map(fn (Reservation $r) => [
                    'id' => $r->id,
                    'reservation_code' => $r->reservation_code,
                    'guest_id' => $r->guest_id,
                    'guest_name' => $r->guest?->full_name,
                ]),
        ]);
    }

    public function store(StoreSpaAppointmentRequest $request): RedirectResponse
    {
        try {
            $this->spaService->bookAppointment($request->validated(), $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Appointment booked.');
    }

    public function updateStatus(UpdateSpaAppointmentStatusRequest $request, SpaAppointment $spaAppointment): RedirectResponse
    {
        try {
            $this->spaService->updateStatus($spaAppointment, $request->string('status')->toString());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Appointment status updated.');
    }

    public function cancel(SpaAppointment $spaAppointment): RedirectResponse
    {
        try {
            $this->spaService->cancelAppointment($spaAppointment);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Appointment cancelled.');
    }

    public function chargeToRoom(Request $request, SpaAppointment $spaAppointment): RedirectResponse
    {
        $request->validate([
            'reservation_id' => ['required', 'exists:reservations,id'],
        ]);

        try {
            $this->spaService->chargeToRoom($spaAppointment, $request->integer('reservation_id'), $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Charge posted to room folio.');
    }

    public function checkAvailability(Request $request): JsonResponse
    {
        $request->validate([
            'spa_therapist_id' => ['required', 'exists:spa_therapists,id'],
            'spa_treatment_id' => ['required', 'exists:spa_treatments,id'],
            'scheduled_at' => ['required', 'date'],
        ]);

        $available = $this->spaService->isAvailable(
            $request->integer('spa_therapist_id'),
            $request->integer('spa_treatment_id'),
            Carbon::parse($request->string('scheduled_at')->toString()),
        );

        return response()->json(['available' => $available]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAppointment(SpaAppointment $appointment): array
    {
        return [
            'id' => $appointment->id,
            'treatment' => $appointment->treatment?->only(['id', 'name', 'duration_minutes', 'price']),
            'therapist' => $appointment->therapist?->only(['id', 'name']),
            'guest' => $appointment->guest?->only(['id', 'full_name']),
            'reservation' => $appointment->reservation?->only(['id', 'reservation_code']),
            'scheduled_at' => $appointment->scheduled_at?->format('Y-m-d H:i'),
            'status' => $appointment->status->value,
            'status_label' => $appointment->status->label(),
            'charged_to_room' => $appointment->charged_to_room,
            'price' => (float) ($appointment->treatment?->price ?? 0),
        ];
    }
}
