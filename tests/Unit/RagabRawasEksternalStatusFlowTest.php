<?php

test('single completed record locks butir creation across non snp modules', function () {
    $rootPath = dirname(__DIR__, 2);

    $modules = [
        'ragab' => [
            'model' => $rootPath.'/app/Models/RagabRecord.php',
            'controller' => $rootPath.'/app/Http/Controllers/Ragab/PerekamanRagabController.php',
            'view' => $rootPath.'/resources/views/layouts/ragab/perekaman.blade.php',
        ],
        'rawas' => [
            'model' => $rootPath.'/app/Models/RawasRecord.php',
            'controller' => $rootPath.'/app/Http/Controllers/Rawas/PerekamanRawasController.php',
            'view' => $rootPath.'/resources/views/layouts/rawas/perekaman.blade.php',
        ],
        'djsn' => [
            'model' => $rootPath.'/app/Models/DjsnRecord.php',
            'controller' => $rootPath.'/app/Http/Controllers/Djsn/PerekamanDjsnController.php',
            'view' => $rootPath.'/resources/views/layouts/djsn/perekaman.blade.php',
        ],
        'eksternal' => [
            'model' => $rootPath.'/app/Models/EksternalRecord.php',
            'controller' => $rootPath.'/app/Http/Controllers/Eksternal/PerekamanEksternalController.php',
            'view' => $rootPath.'/resources/views/layouts/eksternal/perekaman.blade.php',
        ],
    ];

    foreach ($modules as $module) {
        $model = file_get_contents($module['model']);
        $controller = file_get_contents($module['controller']);
        $view = file_get_contents($module['view']);

        expect($model)
            ->toContain('public function isButirAdditionLocked(): bool')
            ->toContain("return \$this->status === 'tuntas'")
            ->toContain("statusTindakLanjut() === 'selesai_tuntas'");

        expect($controller)
            ->toContain('if ($record->isButirAdditionLocked())');

        expect($view)
            ->toContain('@if ($record->isButirAdditionLocked())')
            ->toContain('Butir Tuntas');
    }
});
