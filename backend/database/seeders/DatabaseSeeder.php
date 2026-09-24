<?php

namespace Database\Seeders;

use App\Models\Admission;
use App\Models\Appointment;
use App\Models\Bill;
use App\Models\Doctor;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Staff accounts (password for all demo users: "password") ----
        User::create([
            'name' => 'System Admin',
            'email' => 'admin@carepoint.com',
            'password' => 'carepoint123',
            'role' => User::ROLE_ADMIN,
        ]);

        User::create([
            'name' => 'Fatima Bello',
            'email' => 'receptionist@carepoint.com',
            'password' => 'password',
            'role' => User::ROLE_RECEPTIONIST,
        ]);

        // ---- Doctors (Nigerian Names & @carepoint.com emails) ----
        $doctorsData = [
            ['Dr. Chukwuma Okafor', 'chukwuma.okafor@carepoint.com', 'Cardiology', 'MBBS, FMCP', 15000],
            ['Dr. Amina Ibrahim', 'amina.ibrahim@carepoint.com', 'Pediatrics', 'MBBS, FWACP', 10000],
            ['Dr. Olumide Adeleke', 'olumide.adeleke@carepoint.com', 'Orthopedics', 'MBBS, FWACS', 12500],
            ['Dr. Ngozi Eze', 'ngozi.eze@carepoint.com', 'Dermatology', 'MBBS, FMCPath', 10000],
            ['Dr. Tunde Bakare', 'tunde.bakare@carepoint.com', 'Neurology', 'MBBS, FMCP (Neurology)', 20000],
        ];

        $doctors = collect($doctorsData)->map(function ($d) {
            $user = User::create([
                'name' => $d[0], 
                'email' => $d[1], 
                'password' => 'password',
                'role' => User::ROLE_DOCTOR,
            ]);

            $doctor = Doctor::create([
                'user_id' => $user->id,
                'specialization' => $d[2],
                'qualification' => $d[3],
                'consultation_fee' => $d[4],
                'license_no' => 'MLN-'.rand(10000, 99999),
            ]);

            // Mon-Fri, 09:00-17:00 availability
            foreach (range(1, 5) as $day) {
                $doctor->availabilities()->create([
                    'day_of_week' => $day,
                    'start_time' => '09:00',
                    'end_time' => '17:00',
                ]);
            }

            return $doctor;
        });

        // ---- Patients ----
        // Create user accounts for registered demo patients
        $patientUser1 = User::create([
            'name' => 'Emeka Okafor',
            'email' => 'emeka.okafor@gmail.com',
            'password' => 'password',
            'role' => User::ROLE_PATIENT,
        ]);

        $patientUser2 = User::create([
            'name' => 'Zainab Mohammed',
            'email' => 'zainab.m@yahoo.com',
            'password' => 'password',
            'role' => User::ROLE_PATIENT,
        ]);

        $patientsData = [
            ['Emeka Okafor', $patientUser1->id, 'male', 'O+'],
            ['Zainab Mohammed', $patientUser2->id, 'female', 'A+'],
            ['Blessing Adebayo', null, 'female', 'B+'],
            ['Ibrahim Danjuma', null, 'male', 'O-'],
            ['Chinedu Okoro', null, 'male', 'AB+'],
            ['Aisha Lawal', null, 'female', 'A-'],
        ];

        $patients = collect($patientsData)->map(fn ($p) => Patient::create([
            'user_id' => $p[1],
            'code' => Patient::nextCode(),
            'name' => $p[0],
            'gender' => $p[2],
            'blood_group' => $p[3],
            'dob' => now()->subYears(rand(18, 65))->toDateString(),
            'phone' => '080'.rand(10000000, 99999999),
        ]));

        // ---- Workflow Data: Appointments ----
        $appointments = collect([
            [
                'patient_id' => $patients[0]->id,
                'doctor_id' => $doctors[0]->id,
                'date' => now()->subDays(3)->toDateString(),
                'time' => '10:00',
                'status' => 'completed',
                'reason' => 'Chest pain follow-up',
            ],
            [
                'patient_id' => $patients[1]->id,
                'doctor_id' => $doctors[1]->id,
                'date' => now()->subDays(1)->toDateString(),
                'time' => '11:30',
                'status' => 'completed',
                'reason' => 'Persistent high fever and cough',
            ],
            [
                'patient_id' => $patients[2]->id,
                'doctor_id' => $doctors[2]->id,
                'date' => now()->toDateString(),
                'time' => '14:00',
                'status' => 'confirmed',
                'reason' => 'Severe joint pain in right knee',
            ],
            [
                'patient_id' => $patients[3]->id,
                'doctor_id' => $doctors[3]->id,
                'date' => now()->next('Tuesday')->toDateString(),
                'time' => '10:30',
                'status' => 'pending',
                'reason' => 'Allergic skin rash breakout',
            ],
            [
                'patient_id' => $patients[4]->id,
                'doctor_id' => $doctors[4]->id,
                'date' => now()->next('Wednesday')->toDateString(),
                'time' => '15:00',
                'status' => 'confirmed',
                'reason' => 'Chronic recurring migraines',
            ],
        ])->map(fn ($a) => Appointment::create([
            'patient_id' => $a['patient_id'],
            'doctor_id' => $a['doctor_id'],
            'appointment_date' => $a['date'],
            'appointment_time' => $a['time'],
            'status' => $a['status'],
            'reason' => $a['reason'],
        ]));

        // ---- Workflow Data: Medical Records & Prescriptions ----
        // Record 1 (Emeka Okafor & Dr. Chukwuma Okafor)
        $record1 = MedicalRecord::create([
            'patient_id' => $patients[0]->id,
            'doctor_id' => $doctors[0]->id,
            'appointment_id' => $appointments[0]->id,
            'record_date' => now()->subDays(3)->toDateString(),
            'symptoms' => 'Intermittent chest pain, shortness of breath',
            'diagnosis' => 'Mild hypertension',
            'treatment' => 'Lifestyle changes, medication',
        ]);

        Prescription::create([
            'patient_id' => $patients[0]->id,
            'doctor_id' => $doctors[0]->id,
            'medical_record_id' => $record1->id,
            'prescribed_date' => now()->subDays(3)->toDateString(),
        ])->items()->createMany([
            ['medicine_name' => 'Amlodipine', 'dosage' => '5mg', 'frequency' => '0-0-1', 'duration' => '30 days'],
            ['medicine_name' => 'Aspirin', 'dosage' => '75mg', 'frequency' => '1-0-0', 'duration' => '30 days'],
        ]);

        // Record 2 (Zainab Mohammed & Dr. Amina Ibrahim)
        $record2 = MedicalRecord::create([
            'patient_id' => $patients[1]->id,
            'doctor_id' => $doctors[1]->id,
            'appointment_id' => $appointments[1]->id,
            'record_date' => now()->subDays(1)->toDateString(),
            'symptoms' => 'High fever, sore throat, fatigue',
            'diagnosis' => 'Acute Bronchitis',
            'treatment' => 'Hydration, antibiotics, and rest',
        ]);

        Prescription::create([
            'patient_id' => $patients[1]->id,
            'doctor_id' => $doctors[1]->id,
            'medical_record_id' => $record2->id,
            'prescribed_date' => now()->subDays(1)->toDateString(),
        ])->items()->createMany([
            ['medicine_name' => 'Azithromycin', 'dosage' => '500mg', 'frequency' => '1-0-0', 'duration' => '5 days'],
            ['medicine_name' => 'Paracetamol', 'dosage' => '650mg', 'frequency' => '1-1-1', 'duration' => '3 days'],
        ]);

        // ---- Workflow Data: Admissions ----
        Admission::create([
            'patient_id' => $patients[2]->id,
            'doctor_id' => $doctors[2]->id,
            'ward' => 'General Ward A',
            'bed_no' => 'A-12',
            'diagnosis' => 'Fracture - left tibia',
            'admitted_at' => now()->subDays(4),
            'status' => 'admitted',
        ]);

        Admission::create([
            'patient_id' => $patients[4]->id,
            'doctor_id' => $doctors[4]->id,
            'ward' => 'Private Room B',
            'bed_no' => 'B-04',
            'diagnosis' => 'Severe dehydration & acute migraine attack',
            'admitted_at' => now()->subDays(2),
            'status' => 'admitted',
        ]);

        // ---- Workflow Data: Bills ----
        // Bill 1 for Appointment 1
        $bill1 = Bill::create([
            'bill_no' => Bill::nextBillNo(),
            'patient_id' => $patients[0]->id,
            'appointment_id' => $appointments[0]->id,
            'subtotal' => 15000,
            'tax' => 750,
            'discount' => 0,
            'total' => 15750,
            'billed_at' => now()->subDays(3),
        ]);
        $bill1->items()->create([
            'description' => 'Consultation - Cardiology',
            'quantity' => 1, 
            'unit_price' => 15000, 
            'amount' => 15000,
        ]);

        // Bill 2 for Appointment 2
        $bill2 = Bill::create([
            'bill_no' => Bill::nextBillNo(),
            'patient_id' => $patients[1]->id,
            'appointment_id' => $appointments[1]->id,
            'subtotal' => 10000,
            'tax' => 500,
            'discount' => 0,
            'total' => 10500,
            'billed_at' => now()->subDays(1),
        ]);
        $bill2->items()->create([
            'description' => 'Consultation - Pediatrics',
            'quantity' => 1, 
            'unit_price' => 10000, 
            'amount' => 10000,
        ]);
    }
}