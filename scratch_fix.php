<?php

$dir = new RecursiveDirectoryIterator(__DIR__ . '/app/Filament/Resources');
$iterator = new RecursiveIteratorIterator($dir);

foreach ($iterator as $file) {
    if ($file->isFile() && str_ends_with($file->getFilename(), 'Table.php')) {
        $path = $file->getPathname();
        $content = file_get_contents($path);
        
        $original = $content;
        $content = str_replace(
            'use Filament\Actions\EditAction;',
            'use Filament\Tables\Actions\EditAction;',
            $content
        );
        $content = str_replace(
            'use Filament\Actions\DeleteBulkAction;',
            'use Filament\Tables\Actions\DeleteBulkAction;',
            $content
        );
        $content = str_replace(
            'use Filament\Actions\BulkActionGroup;',
            'use Filament\Tables\Actions\BulkActionGroup;',
            $content
        );

        if ($original !== $content) {
            file_put_contents($path, $content);
            echo "Fixed $path\n";
        }
    }
}
