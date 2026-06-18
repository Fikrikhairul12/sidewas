<?php

test('djsn cluster and sub cluster belong to butir instead of record', function () {
    $basePath = dirname(__DIR__, 2);

    $recordModel = file_get_contents($basePath . '/app/Models/DjsnRecord.php');
    $butirModel = file_get_contents($basePath . '/app/Models/DjsnButir.php');
    $migration = file_get_contents($basePath . '/database/migrations/2026_06_03_093422_create_djsn_tables.php');

    expect($recordModel)
        ->not->toContain("'cluster_id'")
        ->not->toContain("'sub_cluster_id'")
        ->not->toContain('function cluster()')
        ->not->toContain('function subCluster()');

    expect($butirModel)
        ->toContain("'cluster_id'")
        ->toContain("'sub_cluster_id'")
        ->toContain('DjsnCluster::class')
        ->toContain('DjsnSubCluster::class')
        ->not->toContain('RawasCluster::class')
        ->not->toContain('RawasSubCluster::class');

    $recordTableStart = strpos($migration, "Schema::create('tb_record'");
    $butirTableStart = strpos($migration, "Schema::create('tb_butir_djsn'");
    $recordTable = substr($migration, $recordTableStart, $butirTableStart - $recordTableStart);
    $butirTable = substr($migration, $butirTableStart);

    expect($recordTable)
        ->not->toContain("unsignedBigInteger('cluster_id')")
        ->not->toContain("unsignedBigInteger('sub_cluster_id')");

    expect($butirTable)
        ->toContain("unsignedBigInteger('cluster_id')->nullable()")
        ->toContain("unsignedBigInteger('sub_cluster_id')->nullable()");
});

test('djsn perekaman validates cluster on butir form only', function () {
    $basePath = dirname(__DIR__, 2);

    $controller = file_get_contents($basePath . '/app/Http/Controllers/Djsn/PerekamanDjsnController.php');
    $view = file_get_contents($basePath . '/resources/views/layouts/djsn/perekaman.blade.php');
    $script = file_get_contents($basePath . '/resources/js/script.js');

    $storeRecordStart = strpos($controller, 'public function storeRecord');
    $storeButirStart = strpos($controller, 'public function storeButir');
    $requestDeleteStart = strpos($controller, 'public function requestDelete');
    $storeRecord = substr($controller, $storeRecordStart, $storeButirStart - $storeRecordStart);
    $storeButir = substr($controller, $storeButirStart, $requestDeleteStart - $storeButirStart);

    expect($storeRecord)
        ->not->toContain("'cluster_id'")
        ->not->toContain("'sub_cluster_id'");

    expect($storeButir)
        ->toContain("'cluster_id' => ['required', 'integer', 'exists:mysql_djsn.tb_cluster,id']")
        ->toContain("'sub_cluster_id' => ['required', 'integer', 'exists:mysql_djsn.tb_sub_cluster,id']")
        ->toContain("'cluster_id' => \$validated['cluster_id']")
        ->toContain("'sub_cluster_id' => \$validated['sub_cluster_id']");

    expect(substr_count($view, 'name="cluster_id"'))->toBe(2)
        ->and(substr_count($view, 'name="sub_cluster_id"'))->toBe(2)
        ->and($view)->toContain('Tambah Butir Rekomendasi DJSN')
        ->and($view)->toContain('x-model="selectedSubClusterId"');

    expect($script)
        ->toContain('selectedSubClusterId')
        ->toContain('this.selectedClusterId = \'\';')
        ->toContain('this.selectedSubClusterId = \'\';');
});

test('djsn downstream pages load and filter cluster through butir', function () {
    $basePath = dirname(__DIR__, 2);

    $tanggapan = file_get_contents($basePath . '/app/Http/Controllers/Djsn/TanggapanDjsnController.php');
    $tindakLanjut = file_get_contents($basePath . '/app/Http/Controllers/Djsn/TindakLanjutDjsnController.php');
    $reviu = file_get_contents($basePath . '/app/Http/Controllers/Djsn/ReviuDjsnController.php');
    $report = file_get_contents($basePath . '/app/Http/Controllers/Djsn/ReportDjsnController.php');

    foreach ([$tanggapan, $tindakLanjut, $reviu, $report] as $source) {
        expect($source)
            ->not->toContain('record.cluster')
            ->not->toContain('record.subCluster')
            ->not->toContain('butir.record.cluster')
            ->not->toContain('butir.record.subCluster');
    }

    expect($tanggapan)
        ->toContain("'cluster',")
        ->toContain("'subCluster',")
        ->toContain("\$query->where('cluster_id', \$request->cluster_id)")
        ->toContain("\$query->where('sub_cluster_id', \$request->sub_cluster_id)");

    expect($tindakLanjut)
        ->toContain("'cluster',")
        ->toContain("'subCluster',")
        ->toContain("\$butirsRiwayatQuery->where('cluster_id', \$request->cluster_id)")
        ->toContain("\$butirsRiwayatQuery->where('sub_cluster_id', \$request->sub_cluster_id)");

    expect($reviu)
        ->toContain("'butir.cluster'")
        ->toContain("'butir.subCluster'")
        ->toContain("\$butirQuery->where('cluster_id', \$request->cluster_id)")
        ->toContain("\$butirQuery->where('sub_cluster_id', \$request->sub_cluster_id)");

    expect($report)
        ->toContain("'butirDjsn.cluster'")
        ->toContain("'butirDjsn.subCluster'");
});
