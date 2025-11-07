<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PatientAdmission;

class UpdateAdmissionStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admission:update-status {admission_id} {status}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the status of a patient admission';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $admissionId = $this->argument('admission_id');
        $status = $this->argument('status');
        
        $validStatuses = ['admitted', 'discharged', 'transferred', 'active', 'in-labor'];
        
        if (!in_array($status, $validStatuses)) {
            $this->error("Invalid status. Valid statuses are: " . implode(', ', $validStatuses));
            return;
        }
        
        $admission = PatientAdmission::find($admissionId);
        
        if (!$admission) {
            $this->error("Patient admission with ID {$admissionId} not found.");
            return;
        }
        
        $oldStatus = $admission->status;
        $admission->status = $status;
        $admission->save();
        
        $patientName = $admission->patient ? "{$admission->patient->first_name} {$admission->patient->last_name}" : 'Unknown';
        
        $this->info("✅ Updated admission #{$admissionId} ({$patientName}) status from '{$oldStatus}' to '{$status}'");
    }
}
