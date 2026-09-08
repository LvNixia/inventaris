<?php

$resources = [
    'Assets\AssetResource' => ['Aset', 'Aset', 'Manajemen Aset', 'heroicon-o-cube'],
    'AssetServices\AssetServiceResource' => ['Servis Aset', 'Servis Aset', 'Manajemen Aset', 'heroicon-o-wrench'],
    'AssetStatuses\AssetStatusResource' => ['Status Aset', 'Status Aset', 'Referensi', 'heroicon-o-tag'],
    'AssetTransactions\AssetTransactionResource' => ['Riwayat Transaksi', 'Riwayat Transaksi', 'Manajemen Aset', 'heroicon-o-clock'],
    'AttachmentTypes\AttachmentTypeResource' => ['Jenis Lampiran', 'Jenis Lampiran', 'Referensi', 'heroicon-o-paper-clip'],
    'Branches\BranchResource' => ['Cabang', 'Cabang', 'Organisasi', 'heroicon-o-building-office'],
    'Brands\BrandResource' => ['Merek', 'Merek', 'Referensi', 'heroicon-o-swatch'],
    'Categories\CategoryResource' => ['Kategori', 'Kategori', 'Referensi', 'heroicon-o-rectangle-group'],
    'Conditions\ConditionResource' => ['Kondisi', 'Kondisi', 'Referensi', 'heroicon-o-sparkles'],
    'DisposalReasons\DisposalReasonResource' => ['Alasan Pelepasan', 'Alasan Pelepasan', 'Referensi', 'heroicon-o-archive-box-x-mark'],
    'Divisions\DivisionResource' => ['Divisi', 'Divisi', 'Organisasi', 'heroicon-o-users'],
    'Employees\EmployeeResource' => ['Karyawan', 'Karyawan', 'Organisasi', 'heroicon-o-user-group'],
    'HandoverDocuments\HandoverDocumentResource' => ['Surat Serah Terima', 'Surat Serah Terima', 'Dokumen', 'heroicon-o-document-text'],
    'Positions\PositionResource' => ['Jabatan', 'Jabatan', 'Organisasi', 'heroicon-o-briefcase'],
    'ServiceKinds\ServiceKindResource' => ['Jenis Servis', 'Jenis Servis', 'Referensi', 'heroicon-o-cog'],
    'ServiceResults\ServiceResultResource' => ['Hasil Servis', 'Hasil Servis', 'Referensi', 'heroicon-o-check-badge'],
    'Users\UserResource' => ['Pengguna', 'Pengguna', 'Pengaturan', 'heroicon-o-user-circle'],
    'Vendors\VendorResource' => ['Vendor', 'Vendor', 'Pengadaan', 'heroicon-o-truck'],
];

foreach ($resources as $resourcePath => $info) {
    [$label, $plural, $group, $icon] = $info;
    $filePath = __DIR__ . '/app/Filament/Resources/' . $resourcePath . '.php';
    if (file_exists($filePath)) {
        $content = file_get_contents($filePath);
        
        $content = preg_replace('/protected static \?string \$modelLabel = .*;\n/', '', $content);
        $content = preg_replace('/protected static \?string \$pluralModelLabel = .*;\n/', '', $content);
        $content = preg_replace('/protected static \?string \$navigationGroup = .*;\n/', '', $content);
        $content = preg_replace('/protected static \\\\UnitEnum\|string\|null \$navigationGroup = .*;\n/', '', $content);
        $content = preg_replace('/protected static string\|BackedEnum\|null \$navigationIcon = .*;\n/', '', $content);
        
        $injection = <<<PHP
    protected static ?string \$modelLabel = '$label';
    protected static ?string \$pluralModelLabel = '$plural';
    protected static \UnitEnum|string|null \$navigationGroup = '$group';
    protected static string|\BackedEnum|null \$navigationIcon = '$icon';

PHP;
        
        $content = preg_replace('/(protected static \?string \$model = .*;\n)/', "$1\n" . $injection, $content);
        
        file_put_contents($filePath, $content);
        echo "Updated $filePath\n";
    } else {
        echo "Missing $filePath\n";
    }
}
