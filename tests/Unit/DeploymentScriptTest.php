<?php

use Symfony\Component\Process\Process;

test('cpanel deployment does not require rsync', function () {
    $deploymentScript = file_get_contents(dirname(__DIR__, 2).'/deploy-cpanel.sh');

    expect($deploymentScript)
        ->not->toContain('rsync')
        ->toContain('copy_missing_tree')
        ->toContain('/bin/cp -a -n --')
        ->toContain('copy_public_tree');
});

test('public deployment preserves server managed entries', function () {
    $deploymentScript = file_get_contents(dirname(__DIR__, 2).'/deploy-cpanel.sh');

    expect($deploymentScript)
        ->toContain('.htaccess|index.php|storage|.well-known|cgi-bin')
        ->toContain('/bin/cp -a -- "$source_entry" "$PUBLIC_PATH"/');
});

test('cpanel deployment falls back to compatible legacy dependencies', function () {
    $deploymentScript = file_get_contents(dirname(__DIR__, 2).'/deploy-cpanel.sh');

    expect($deploymentScript)
        ->toContain('COMPOSER_AVAILABLE=false')
        ->toContain('dependency akan disalin dari application root lama')
        ->toContain('hash_file("sha256", $argv[1]) === hash_file("sha256", $argv[2])')
        ->toContain('[[ ! -f "$APP_PATH/vendor/autoload.php" ]]')
        ->toContain('copy_missing_tree "$LEGACY_APP_PATH/vendor" "$APP_PATH/vendor"')
        ->toContain('"$PHP_BIN" artisan package:discover --ansi');
});

test('R7 deployment checks activation after migration and before publishing files', function () {
    $script = file_get_contents(dirname(__DIR__, 2).'/deploy-cpanel.sh');
    expect(strpos($script, 'artisan migrate --force'))->toBeLessThan(strpos($script, 'workflow:check-revisi --if-enabled'))
        ->and(strpos($script, 'workflow:check-revisi --if-enabled'))->toBeLessThan(strpos($script, "log 'Menyinkronkan public Laravel ke document root.'"))
        ->and($script)->toContain('activation_check_failed=true', 'Maintenance gagal dimulai; deployment dihentikan sebelum migrasi.');
});

test('R7 deployment trap retains maintenance only for a failed activation check', function (bool $activationFailed, int $exitCode, bool $expectUp) {
    $bash = getenv('SIDEWAS_TEST_BASH') ?: (PHP_OS_FAMILY === 'Windows' ? null : '/bin/bash');
    if (! $bash || ! is_file($bash)) {
        $this->markTestSkipped('Set SIDEWAS_TEST_BASH untuk uji shell deployment.');
    }
    $root = dirname(__DIR__, 2);
    $directory = $root.'/tmp/r7/deployment-'.bin2hex(random_bytes(5));
    mkdir($directory, 0700, true);
    $stub = $directory.'/php-stub.sh';
    $log = $directory.'/up.log';
    file_put_contents($stub, "#!/bin/sh\nprintf '%s\\n' \"\$2\" >> \"\$R7_UP_LOG\"\n");
    chmod($stub, 0700);
    $script = file_get_contents($root.'/deploy-cpanel.sh');
    $start = strpos($script, 'finish_deployment() {');
    $end = strpos($script, 'trap finish_deployment EXIT', $start);
    $function = substr($script, $start, $end - $start);
    $probe = 'set -Eeuo pipefail'."\n".'log() { printf "%s\n" "$1"; }'."\n"
        .'PHP_BIN="$R7_PHP_STUB"; APP_PATH="$R7_APP_PATH"; maintenance_enabled=true; activation_check_failed="$R7_ACTIVATION_FAILED"'."\n"
        .$function."\n".'trap finish_deployment EXIT'."\n".'exit "$R7_EXIT_CODE"';
    $process = new Process([$bash, '-c', $probe], $root, [
        'R7_PHP_STUB' => str_replace('\\', '/', $stub), 'R7_APP_PATH' => str_replace('\\', '/', $directory), 'R7_UP_LOG' => str_replace('\\', '/', $log),
        'R7_ACTIVATION_FAILED' => $activationFailed ? 'true' : 'false', 'R7_EXIT_CODE' => (string) $exitCode,
    ]);
    $process->setTimeout(15)->run();
    expect($process->getExitCode())->toBe($exitCode, $process->getOutput().$process->getErrorOutput())
        ->and(is_file($log))->toBe($expectUp);
    if ($expectUp) {
        expect(trim(file_get_contents($log)))->toBe('up');
    }
})->with([[true, 1, false], [false, 1, true], [false, 0, true]]);
