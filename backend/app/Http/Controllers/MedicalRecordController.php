<?php

namespace App\Http\Controllers;

use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MedicalRecordController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return MedicalRecord::with(['patient:id,code,name', 'doctor.user:id,name'])
            ->when($user->role === User::ROLE_DOCTOR, fn ($q) => $q->where('doctor_id', $user->doctor?->id))
            ->when($user->role === User::ROLE_PATIENT, fn ($q) => $q->where('patient_id', $user->patient?->id))
            ->when($request->query('patient_id'), fn ($q, $id) => $q->where('patient_id', $id))
            ->latest('record_date')
            ->paginate(min(max($request->integer('per_page', 15), 1), 100));
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'appointment_id' => ['nullable', 'exists:appointments,id'],
            'record_date' => ['required', 'date'],
            'symptoms' => ['nullable', 'string'],
            'diagnosis' => ['required', 'string'],
            'treatment' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        // Doctors author records as themselves; admin must specify.
        if ($user->role === User::ROLE_DOCTOR) {
            if (! $user->doctor) {
                return response()->json(['message' => 'Doctor profile not found.'], 422);
            }
            $data['doctor_id'] = $user->doctor->id;
        } else {
            $data['doctor_id'] = $request->validate(['doctor_id' => ['required', 'exists:doctors,id']])['doctor_id'];
        }

        if (! empty($data['appointment_id'])) {
            $appointment = \App\Models\Appointment::findOrFail($data['appointment_id']);
            if ($appointment->patient_id !== (int) $data['patient_id'] || $appointment->doctor_id !== (int) $data['doctor_id']) {
                throw ValidationException::withMessages([
                    'appointment_id' => 'The appointment must belong to the selected patient and doctor.',
                ]);
            }
        }

        return response()->json(
            MedicalRecord::create($data)->load(['patient:id,code,name', 'doctor.user:id,name']),
            201
        );
    }

    public function show(Request $request, MedicalRecord $medicalRecord)
    {
        $user = $request->user();
        if ($user->role === User::ROLE_PATIENT && $medicalRecord->patient_id !== $user->patient?->id) {
            abort(403);
        }
        if ($user->role === User::ROLE_DOCTOR && $medicalRecord->doctor_id !== $user->doctor?->id) {
            abort(403);
        }

        return $medicalRecord->load(['patient', 'doctor.user:id,name', 'prescriptions.items']);
    }

    public function update(Request $request, MedicalRecord $medicalRecord)
    {
        $user = $request->user();
        if ($user->role === User::ROLE_DOCTOR && $medicalRecord->doctor_id !== $user->doctor?->id) {
            return response()->json(['message' => 'You can only edit your own records.'], 403);
        }

        $data = $request->validate([
            'record_date' => ['sometimes', 'date'],
            'symptoms' => ['nullable', 'string'],
            'diagnosis' => ['sometimes', 'string'],
            'treatment' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $medicalRecord->update($data);

        return $medicalRecord->load(['patient:id,code,name', 'doctor.user:id,name']);
    }

    public function destroy(MedicalRecord $medicalRecord)
    {
        $user = request()->user();
        if ($user->role === User::ROLE_DOCTOR && $medicalRecord->doctor_id !== $user->doctor?->id) {
            abort(403);
        }

        $medicalRecord->delete();

        return response()->json(['message' => 'Medical record deleted.']);
    }
}
