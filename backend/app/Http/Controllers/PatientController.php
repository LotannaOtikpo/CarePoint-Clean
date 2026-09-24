<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        return Patient::query()
            ->when($request->query('search'), fn ($q, $s) => $q->where(
                fn ($q) => $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%")
            ))
            ->latest()
            ->paginate(min(max($request->integer('per_page', 15), 1), 100));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $patient = DB::transaction(function () use ($data) {
            // 1. Create a User account for the patient so they can log in
            // User model has 'password' => 'hashed' cast, so pass plain text to avoid double hashing
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'] ?? ($data['phone'] ? $data['phone'].'@carepoint.local' : 'patient_'.time().'@carepoint.local'),
                'password' => $data['password'] ?? 'password123',
                'role' => User::ROLE_PATIENT,
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
            ]);

            // 2. Create the Patient record linked to that User (exclude password)
            $patientData = collect($data)->except(['password'])->all();
            $patientData['code'] = Patient::nextCode();
            $patientData['user_id'] = $user->id;

            return Patient::create($patientData);
        });

        return response()->json($patient, 201);
    }

    public function show(Patient $patient)
    {
        $user = request()->user();
        if ($user->role === User::ROLE_DOCTOR) {
            $hasAssignment = Appointment::where('patient_id', $patient->id)
                ->where('doctor_id', $user->doctor?->id)
                ->exists()
                || MedicalRecord::where('patient_id', $patient->id)
                    ->where('doctor_id', $user->doctor?->id)
                    ->exists();

            abort_unless($hasAssignment, 403, 'Not authorized for this patient.');
        }

        return $patient->load([
            'appointments.doctor.user', 'admissions.doctor.user',
            'medicalRecords.doctor.user', 'bills',
        ]);
    }

    public function update(Request $request, Patient $patient)
    {
        $data = $this->validated($request, $patient);

        DB::transaction(function () use ($data, $patient) {
            // 1. Update the linked User account to keep User Management in sync
            if ($patient->user_id && $patient->user) {
                $userData = [
                    'name' => $data['name'],
                    'email' => $data['email'] ?? ($data['phone'] ? $data['phone'].'@carepoint.local' : $patient->user->email),
                    'phone' => $data['phone'] ?? null,
                ];

                // Only update the password if a new one was provided
                if (!empty($data['password'])) {
                    $userData['password'] = $data['password'];
                }

                $patient->user->update($userData);
            }

            // 2. Update the Patient record (exclude password)
            $patientData = collect($data)->except(['password'])->all();
            $patient->update($patientData);
        });

        return $patient->load('user');
    }

    public function destroy(Patient $patient)
    {
        // Delete the linked user account as well if it exists
        if ($patient->user_id) {
            User::where('id', $patient->user_id)->delete();
        }

        $patient->delete();

        return response()->json(['message' => 'Patient deleted.']);
    }

    private function validated(Request $request, ?Patient $patient = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'dob' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:male,female,other'],
            'blood_group' => ['nullable', 'string', 'max:5'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => [
                'nullable',
                'email',
                Rule::unique('users', 'email')->ignore($patient?->user_id),
            ],
            'address' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8'], 
        ]);
    }
}