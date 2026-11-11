<?php

use App\Domain\Relais\Actions\EnrolAppRelais;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L'inscription depuis l'application n'attend plus de validation (MOB-01) : les comptes restés « en attente »
 * deviennent actifs et Relais, comme ceux qui s'inscrivent désormais. Le statut « pending » n'existe plus.
 */
return new class extends Migration
{
    public function up(): void
    {
        $pendingIds = DB::table('users')->where('status', 'pending')->pluck('id');

        foreach ($pendingIds as $id) {
            DB::table('users')->where('id', $id)->update(['status' => 'active', 'updated_at' => now()]);

            $user = User::query()->whereKey($id)->first();

            if ($user !== null) {
                app(EnrolAppRelais::class)->handle($user);
            }
        }
    }

    public function down(): void
    {
        // Irréversible : on ne sait plus quels comptes étaient en attente.
    }
};
