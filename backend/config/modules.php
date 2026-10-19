<?php

/*
|--------------------------------------------------------------------------
| Python modules
|--------------------------------------------------------------------------
|
| Small Python programs that Laravel runs as separate processes: a JSON
| object goes in on stdin and a JSON object comes out on stdout. Each module
| is a folder with a module.json and its script (see modules/README.md).
| Modules come from this folder and from Composer packages of type
| "jalaops-module" required in composer.json, so both are part of the code:
| they are added and updated through pull requests (a package's version is
| pinned in composer.lock), never uploaded from the app. Composer installs
| packages into modules/packages, which git ignores.
|
*/

return [

    // The folder with one subfolder per module.
    'path' => env('MODULES_PATH', base_path('modules')),

    // Whether modules also come from installed packages of type jalaops-module.
    'packages' => (bool) env('MODULES_PACKAGES', true),

    // The Python interpreter that runs the modules.
    'python' => env('PYTHON_BINARY', 'python3'),

    // Where modules with "storage": true keep their data, one folder per
    // module (given to the module in JALAOPS_DATA_DIR).
    'storage' => env('MODULES_STORAGE_PATH', storage_path('app/modules')),

    // The places in the app where module widgets can be shown (action
    // hooks), with the names users see. A widget's module.json picks one,
    // modules:place changes it for everyone, and each user can move or hide
    // it in their own browser.
    'hooks' => [
        'dashboard.top' => 'Etusivun yläosa',
        'dashboard.after_counts' => 'Tilanteen jälkeen',
        'dashboard.after_overview' => 'Tilannekatsauksen jälkeen',
        'dashboard.bottom' => 'Etusivun alaosa',
    ],

];
