<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->unique('user_id', 'doctors_user_id_unique');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->unique('user_id', 'patients_user_id_unique');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->index(['patient_id', 'status'], 'appointments_patient_status_index');
            $table->index(['doctor_id', 'appointment_date'], 'appointments_doctor_date_index');
        });

        Schema::table('admissions', function (Blueprint $table) {
            $table->index(['patient_id', 'status'], 'admissions_patient_status_index');
            $table->index(['doctor_id', 'status'], 'admissions_doctor_status_index');
        });

        Schema::table('medical_records', function (Blueprint $table) {
            $table->index(['patient_id', 'record_date'], 'medical_records_patient_date_index');
            $table->index(['doctor_id', 'record_date'], 'medical_records_doctor_date_index');
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->index(['patient_id', 'prescribed_date'], 'prescriptions_patient_date_index');
            $table->index(['doctor_id', 'prescribed_date'], 'prescriptions_doctor_date_index');
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->index(['patient_id', 'status'], 'bills_patient_status_index');
            $table->index(['status', 'billed_at'], 'bills_status_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropIndex('bills_patient_status_index');
            $table->dropIndex('bills_status_date_index');
        });
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropIndex('prescriptions_patient_date_index');
            $table->dropIndex('prescriptions_doctor_date_index');
        });
        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropIndex('medical_records_patient_date_index');
            $table->dropIndex('medical_records_doctor_date_index');
        });
        Schema::table('admissions', function (Blueprint $table) {
            $table->dropIndex('admissions_patient_status_index');
            $table->dropIndex('admissions_doctor_status_index');
        });
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_patient_status_index');
            $table->dropIndex('appointments_doctor_date_index');
        });
        Schema::table('patients', function (Blueprint $table) {
            $table->dropUnique('patients_user_id_unique');
        });
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropUnique('doctors_user_id_unique');
        });
    }
};