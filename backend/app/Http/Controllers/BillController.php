<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return Bill::with(['patient:id,code,name', 'items'])
            ->when(in_array($user->role, [User::ROLE_PATIENT, User::ROLE_DOCTOR], true), fn ($q) => $q->where('patient_id', $user->patient?->id))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->latest('billed_at')
            ->paginate(min(max($request->integer('per_page', 15), 1), 100));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'admission_id' => ['nullable', 'exists:admissions,id'],
            'appointment_id' => ['nullable', 'exists:appointments,id'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $linkedPatients = collect();
        if (! empty($data['admission_id'])) {
            $linkedPatients->push(\App\Models\Admission::findOrFail($data['admission_id'])->patient_id);
        }
        if (! empty($data['appointment_id'])) {
            $linkedPatients->push(\App\Models\Appointment::findOrFail($data['appointment_id'])->patient_id);
        }
        if ($linkedPatients->contains(fn ($patientId) => (int) $patientId !== (int) $data['patient_id'])) {
            throw ValidationException::withMessages([
                'patient_id' => 'The linked admission or appointment belongs to a different patient.',
            ]);
        }

        $bill = DB::transaction(function () use ($data) {
            $subtotal = collect($data['items'])
                ->sum(fn ($i) => $i['quantity'] * $i['unit_price']);
            $tax = $data['tax'] ?? 0;
            $discount = $data['discount'] ?? 0;

            if ($discount > $subtotal + $tax) {
                throw ValidationException::withMessages([
                    'discount' => 'Discount cannot exceed the subtotal plus tax.',
                ]);
            }

            $bill = Bill::create([
                'bill_no' => Bill::nextBillNo(),
                'patient_id' => $data['patient_id'],
                'admission_id' => $data['admission_id'] ?? null,
                'appointment_id' => $data['appointment_id'] ?? null,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => $discount,
                'total' => max(0, $subtotal + $tax - $discount),
                'billed_at' => now(),
            ]);

            $bill->items()->createMany(collect($data['items'])->map(fn ($i) => [
                ...$i,
                'amount' => $i['quantity'] * $i['unit_price'],
            ])->all());

            return $bill;
        });

        return response()->json($bill->load(['patient:id,code,name', 'items']), 201);
    }

    public function show(Request $request, Bill $bill)
    {
        $user = $request->user();
        if ($user->role === User::ROLE_PATIENT && $bill->patient_id !== $user->patient?->id) {
            abort(403);
        }
        if ($user->role === User::ROLE_DOCTOR) {
            abort(403);
        }

        return $bill->load(['patient', 'items', 'admission', 'appointment']);
    }

    /** Record a payment against the bill. */
    public function pay(Request $request, Bill $bill)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'max:50'],
        ]);

        $bill = DB::transaction(function () use ($bill, $data) {
            $locked = Bill::lockForUpdate()->findOrFail($bill->id);
            if (in_array($locked->status, ['paid', 'cancelled'], true)) {
                throw ValidationException::withMessages(['bill' => "Bill is already {$locked->status}."]);
            }

            $due = round((float) $locked->total - (float) $locked->paid_amount, 2);
            if ((float) $data['amount'] > $due) {
                throw ValidationException::withMessages(['amount' => 'Payment cannot exceed the outstanding balance.']);
            }

            $paid = round((float) $locked->paid_amount + (float) $data['amount'], 2);
            $locked->update([
                'paid_amount' => $paid,
                'payment_method' => $data['payment_method'],
                'status' => $paid >= (float) $locked->total ? 'paid' : 'partially_paid',
            ]);

            return $locked;
        });

        return $bill->load(['patient:id,code,name', 'items']);
    }

    /** Update the status of a bill directly. */
    public function updateStatus(Request $request, Bill $bill)
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', Bill::STATUSES)],
        ]);

        if ($bill->status === 'paid' && $data['status'] !== 'paid') {
            throw ValidationException::withMessages(['status' => 'Paid bills cannot move to another status.']);
        }
        if ($bill->status === 'cancelled' && $data['status'] !== 'cancelled') {
            throw ValidationException::withMessages(['status' => 'Cancelled bills cannot be reopened.']);
        }

        $bill->update(['status' => $data['status']]);

        return $bill->load(['patient:id,code,name', 'items']);
    }

    public function cancel(Bill $bill)
    {
        if ($bill->status === 'paid') {
            return response()->json(['message' => 'A paid bill cannot be cancelled.'], 422);
        }

        $bill->update(['status' => 'cancelled']);

        return $bill;
    }

    public function destroy(Bill $bill)
    {
        $bill->delete();

        return response()->json(['message' => 'Bill deleted.']);
    }
}
