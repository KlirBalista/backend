<?php

namespace App\Console\Commands;

use App\Models\Patient;
use Illuminate\Console\Command;

class RemoveAllPatients extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'patients:remove-all {--confirm : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove all patients from the patients table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $patientCount = Patient::count();

        if ($patientCount === 0) {
            $this->info('No patients found in the database.');
            return;
        }

        $this->warn("This will permanently delete {$patientCount} patients from the database.");

        if (!$this->option('confirm')) {
            if (!$this->confirm('Are you sure you want to proceed?')) {
                $this->info('Operation cancelled.');
                return;
            }
        }

        $this->info('Removing all patients...');
        
        try {
            // Using chunk to handle large datasets efficiently
            Patient::chunk(100, function ($patients) {
                foreach ($patients as $patient) {
                    $patient->delete();
                }
            });

            $this->info("Successfully removed {$patientCount} patients from the database.");
        } catch (\Exception $e) {
            $this->error('Failed to remove patients: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}