<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeleteUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:delete {--ids=} {--emails=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete users by IDs or emails, including related records (subscriptions, roles, staff, tokens)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $ids = $this->option('ids');
        $emails = $this->option('emails');

        $idList = $ids ? collect(explode(',', $ids))->filter()->map(fn($v) => (int) trim($v))->all() : [];
        $emailList = $emails ? collect(explode(',', $emails))->filter()->map(fn($v) => trim($v))->all() : [];

        if (empty($idList) && empty($emailList)) {
            $this->error('Please provide --ids=1,2,3 and/or --emails=a@b.com,c@d.com');
            return 1;
        }

        $users = User::query()
            ->when(!empty($idList), fn($q) => $q->whereIn('id', $idList))
            ->when(!empty($emailList), fn($q) => $q->orWhereIn('email', $emailList))
            ->get();

        if ($users->isEmpty()) {
            $this->info('No matching users found.');
            return 0;
        }

        $this->warn('Users to be deleted:');
        foreach ($users as $u) {
            $this->line(" - ID {$u->id}: {$u->email} ({$u->firstname} {$u->lastname})");
        }

        if (!$this->confirm('Proceed with deletion?')) {
            $this->info('Aborted.');
            return 0;
        }

        DB::beginTransaction();
        try {
            $userIds = $users->pluck('id')->all();

            // Disable FKs for sqlite safety
            Schema::disableForeignKeyConstraints();

            // Related cleanups
            DB::table('birth_care_subscriptions')->whereIn('user_id', $userIds)->delete();
            DB::table('birth_cares')->whereIn('user_id', $userIds)->delete();
            DB::table('user_birth_roles')->whereIn('user_id', $userIds)->delete();
            DB::table('birth_care_staff')->whereIn('user_id', $userIds)->delete();
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->whereIn('tokenable_id', $userIds)
                ->delete();

            // Finally delete the users
            DB::table('users')->whereIn('id', $userIds)->delete();

            Schema::enableForeignKeyConstraints();

            DB::commit();
            $this->info('Deleted '.count($userIds).' user(s) successfully.');
            return 0;
        } catch (\Throwable $e) {
            DB::rollBack();
            Schema::enableForeignKeyConstraints();
            $this->error('Failed: '.$e->getMessage());
            return 1;
        }
    }
}