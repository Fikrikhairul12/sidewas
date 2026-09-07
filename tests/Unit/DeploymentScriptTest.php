<?php

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
