<?php

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;

$sa = User::where('email', 'superadmin@demo.test')->firstOrFail();
$admin = User::where('email', 'admin@demo.test')->firstOrFail();
$policy = new UserPolicy();
$gate = app(GateContract::class);

$terdaftar = $gate->getPolicyFor(User::class);
echo 'Policy terdaftar untuk User: ' . (is_object($terdaftar) ? get_class($terdaftar) : 'TIDAK ADA') . "\n\n";

$baris = function (string $label, User $aktor, User $target, string $aksi) use ($policy): void {
    echo str_pad($label, 34)
        . ' lewat Gate=' . var_export($aktor->can($aksi, $target), true)
        . '   policy langsung=' . var_export($policy->{$aksi}($aktor, $target), true) . "\n";
};

foreach (['view', 'update', 'delete'] as $aksi) {
    $baris("admin -> superadmin ({$aksi})", $admin, $sa, $aksi);
}
$baris('superadmin -> dirinya sendiri (delete)', $sa, $sa, 'delete');
$baris('admin -> dirinya sendiri (delete)', $admin, $admin, 'delete');

$ref = new ReflectionProperty($gate, 'beforeCallbacks');
$ref->setAccessible(true);
$callbacks = $ref->getValue($gate);

echo "\nJumlah Gate::before terdaftar: " . count($callbacks) . "\n";
foreach ($callbacks as $i => $cb) {
    $rf = new ReflectionFunction(Closure::fromCallable($cb));
    echo "  #{$i} " . str_replace(base_path() . '/', '', (string) $rf->getFileName()) . ':' . $rf->getStartLine() . "\n";
}
