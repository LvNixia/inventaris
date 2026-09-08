<?php

$dir = new RecursiveDirectoryIterator(__DIR__ . '/app/Filament/Resources');
$iterator = new RecursiveIteratorIterator($dir);

foreach ($iterator as $file) {
    if ($file->isFile() && str_ends_with($file->getFilename(), 'Table.php')) {
        $path = $file->getPathname();
        $content = file_get_contents($path);
        
        $original = $content;
        $content = str_replace(
            'use Filament\Tables\Actions\EditAction;',
            'use Filament\Actions\EditAction;',
            $content
        );
        $content = str_replace(
            'use Filament\Tables\Actions\DeleteBulkAction;',
            'use Filament\Actions\DeleteBulkAction;',
            $content
        );
        $content = str_replace(
            'use Filament\Tables\Actions\BulkActionGroup;',
            'use Filament\Actions\BulkActionGroup;',
            $content
        );
        $content = str_replace(
            'use Filament\Tables\Actions\Action;',
            'use Filament\Actions\Action;',
            $content
        );

        if ($original !== $content) {
            file_put_contents($path, $content);
            echo "Reverted $path\n";
        }
    }
}
