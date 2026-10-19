<?php

/*
|--------------------------------------------------------------------------
| Python modules
|--------------------------------------------------------------------------
|
| Small Python programs that Laravel runs as separate processes: a JSON
| object goes in on stdin and a JSON object comes out on stdout. Each module
| is a folder with a module.json and its script (see modules/README.md).
| Modules come only from this folder, which is part of the code: they are
| added through pull requests, never uploaded from the app.
|
*/

return [

    // The folder with one subfolder per module.
    'path' => env('MODULES_PATH', base_path('modules')),

    // The Python interpreter that runs the modules.
    'python' => env('PYTHON_BINARY', 'python3'),

];
