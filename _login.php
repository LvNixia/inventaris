<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

echo "provider auth : " . config('auth.providers.users.model') . "\n";
echo "session driver: " . config('session.driver') . "\n";
echo "guard default : " . config('auth.defaults.guard') . "\n\n";

echo "=== Pengguna di database ===\n";
foreach (\App\Models\User::withoutGlobalScopes()->get() as $u) {
    printf("  #%d %-28s role=%-12s branch_id=%-4s aktif=%s hash=%s\n",
        $u->id, $u->email, $u->role?->value ?? '(null)', $u->branch_id ?? '-',
        var_export($u->is_active, true),
        str_starts_with($u->getAttributes()['password'] ?? '', '$2y$') ? 'bcrypt ok' : 'BUKAN HASH');
}

DB::beginTransaction();
try {
    // Uji jalur login sesungguhnya: attempt() untuk tiap peran.
    foreach ([['admin_pusat', null], ['admin_cabang', \App\Models\Branch::first()?->id]] as [$role, $branchId]) {
        $email = "uji.$role@contoh.test";
        \App\Models\User::create([
            'name' => 'Uji ' . $role, 'email' => $email, 'password' => 'rahasia123',
            'role' => $role, 'branch_id' => $branchId, 'is_active' => true,
        ]);

        Auth::logout();
        $ok = Auth::attempt(['email' => $email, 'password' => 'rahasia123']);
        printf("  attempt %-14s -> %s\n", $role, $ok ? 'BERHASIL' : 'GAGAL');

        if ($ok) {
            // Setelah masuk, coba ambil ulang user seperti yang dilakukan sesi tiap request.
            $id = Auth::id();
            Auth::logout();
            $found = Auth::loginUsingId($id);
            printf("     ambil ulang dari sesi -> %s\n", $found ? 'ketemu' : 'TIDAK KETEMU (auth putus tiap request)');
            Auth::logout();
        }
    }
} catch (\Throwable $e) {
    echo 'ERROR: ' . get_class($e) . ': ' . $e->getMessage() . "\n";
} finally {
    DB::rollBack();
}
