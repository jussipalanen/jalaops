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
| pinned in composer.lock), never uploaded from the app.
|
*/

return [

    // The folder with one subfolder per module.
    'path' => env('MODULES_PATH', base_path('modules')),

    // Whether modules also come from installed packages of type jalaops-module.
    'packages' => (bool) env('MODULES_PACKAGES', true),

    // The Python interpreter that runs the modules.
    'python' => env('PYTHON_BINARY', 'python3'),

];
