<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(TestCase::class);

test('dashboard route uses statistik controller', function () {
    $route = Route::getRoutes()->getByName('dashboard');

    expect($route)->not->toBeNull()
        ->and($route->getActionName())->toBe(DashboardController::class . '@index');
});

test('dashboard view exposes chart canvases and data payload', function () {
    $view = file_get_contents(resource_path('views/dashboard.blade.php'));

    expect($view)
        ->toContain('suratPerJenisChart')
        ->toContain('Statistik Tindak Lanjut Hasil Pengawasan')
        ->toContain('butirProgressStatuses')
        ->toContain('dashboard-chart-data');
});

test('dashboard chart script loads chart js', function () {
    $script = file_get_contents(resource_path('js/dashboard-chart.js'));

    expect($script)
        ->toContain("import Chart from 'chart.js/auto'")
        ->toContain('makeBarChart')
        ->toContain('stacked: Boolean(dataset.datasets)');
});
