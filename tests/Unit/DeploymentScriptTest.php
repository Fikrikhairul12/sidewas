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

test('BUG-001 deployment melindungi salinan public lama tanpa mengubah isi berkas', function () {
    $bash = getenv('SIDEWAS_TEST_BASH') ?: (PHP_OS_FAMILY === 'Windows' ? null : '/bin/bash');
    if (! $bash || ! is_file($bash)) {
        $this->markTestSkipped('Set SIDEWAS_TEST_BASH untuk uji shell deployment.');
    }
    $root = dirname(__DIR__, 2);
    $directory = $root.'/tmp/bug-001/deployment-'.bin2hex(random_bytes(5));
    $documents = $directory.'/storage/app/public/dokumen/salinan-kompilasi';
    mkdir($documents, 0700, true);
    file_put_contents($documents.'/bukti.pdf', 'SALINAN LAMA TETAP UTUH');
    $script = file_get_contents($root.'/deploy-cpanel.sh');
    $start = strpos($script, 'protect_compilation_documents() {');
    $end = strpos($script, "\nPHP_BIN=", $start);
    $function = substr($script, $start, $end - $start);
    $process = new Process([$bash, '-c', 'set -Eeuo pipefail'."\n".$function."\nprotect_compilation_documents\nprotect_compilation_documents"], $root, [
        'APP_PATH' => str_replace('\\', '/', $directory),
        'REPORT_DOCUMENT_ACCESS_TEMPLATE' => str_replace('\\', '/', $root.'/resources/deployment/report-documents.htaccess'),
    ]);
    $process->setTimeout(15)->run();
    expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput())
        ->and(file_get_contents($documents.'/bukti.pdf'))->toBe('SALINAN LAMA TETAP UTUH')
        ->and(file_get_contents($documents.'/.htaccess'))->toBe(file_get_contents($root.'/resources/deployment/report-documents.htaccess'))
        ->and(file_get_contents($documents.'/.htaccess'))->toContain('Require all denied', 'Deny from all');
});

test('BUG-001 Apache menolak URL salinan lama meskipun berkasnya tersedia', function () {
    $httpd = getenv('SIDEWAS_TEST_HTTPD');
    if (! $httpd || ! is_file($httpd)) {
        $this->markTestSkipped('Set SIDEWAS_TEST_HTTPD untuk uji akses HTTP Apache.');
    }
    $directory = str_replace('\\', '/', dirname(__DIR__, 2)).'/tmp/bug-001/httpd-'.bin2hex(random_bytes(5));
    $public = $directory.'/public';
    $documents = $public.'/dokumen/salinan-kompilasi';
    mkdir($documents, 0700, true);
    file_put_contents($public.'/health.txt', 'HTTP READY');
    file_put_contents($documents.'/bukti.pdf', 'DOKUMEN PRIVAT');
    copy(dirname(__DIR__, 2).'/resources/deployment/report-documents.htaccess', $documents.'/.htaccess');
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);
    $apache = str_replace('\\', '/', dirname($httpd, 2));
    $config = 'ServerRoot "'.$apache.'"'."\n"
        .'ServerName localhost'."\n".'Listen 127.0.0.1:'.$port."\n"
        .'LoadModule authz_core_module modules/mod_authz_core.so'."\n"
        .'LoadModule authz_host_module modules/mod_authz_host.so'."\n"
        .'ErrorLog "'.$directory.'/error.log"'."\n".'PidFile "'.$directory.'/httpd.pid"'."\n"
        .'DocumentRoot "'.$public.'"'."\n".'<Directory "'.$public.'">'."\n"
        .'AllowOverride AuthConfig'."\n".'Require all granted'."\n".'</Directory>'."\n";
    file_put_contents($directory.'/httpd.conf', $config);
    $server = new Process([$httpd, '-X', '-f', $directory.'/httpd.conf']);
    $server->setTimeout(15)->start();
    $client = curl_init();
    curl_setopt_array($client, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT_MS => 200]);
    try {
        $ready = false;
        for ($attempt = 0; $attempt < 50; $attempt++) {
            curl_setopt($client, CURLOPT_URL, 'http://127.0.0.1:'.$port.'/health.txt');
            if (curl_exec($client) === 'HTTP READY') {
                $ready = true;
                break;
            }
            usleep(100000);
        }
        expect($ready)->toBeTrue($server->getErrorOutput());
        curl_setopt($client, CURLOPT_URL, 'http://127.0.0.1:'.$port.'/dokumen/salinan-kompilasi/bukti.pdf');
        $response = curl_exec($client);
        expect(curl_getinfo($client, CURLINFO_RESPONSE_CODE))->toBe(403)
            ->and($response)->not->toContain('DOKUMEN PRIVAT')
            ->and(file_get_contents($documents.'/bukti.pdf'))->toBe('DOKUMEN PRIVAT');
    } finally {
        curl_close($client);
        $server->stop(1);
    }
});
