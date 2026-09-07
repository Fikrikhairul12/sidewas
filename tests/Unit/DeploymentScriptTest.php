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
