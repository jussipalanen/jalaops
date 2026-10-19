<?php

namespace App\Services\Modules;

use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use JsonException;
use stdClass;

/**
 * Runs a Python module as a separate process: the input goes to it as a JSON
 * object on stdin, and it must print a JSON object on stdout and exit with 0.
 */
class ModuleRunner
{
    /**
     * @param  string  $storage  The folder with each module's data folder
     */
    public function __construct(private readonly string $python, private readonly string $storage) {}

    public static function fromConfig(): self
    {
        return new self(config('modules.python'), config('modules.storage'));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ModuleException when the module is off, fails, takes too long or prints something else than a JSON object
     */
    public function run(PythonModule $module, array $input = []): array
    {
        if (! $module->enabled) {
            throw new ModuleException("Module '{$module->id}' is disabled.", 'Moduuli ei ole käytössä.');
        }

        try {
            $result = Process::path($module->directory)
                ->timeout($module->timeout)
                ->env($this->environment($module))
                ->input(json_encode((object) $input, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))
                // -E -s: ignore PYTHON* variables and the user's site-packages,
                // -B: write no .pyc files, -X utf8: UTF-8 everywhere.
                ->run([$this->python, '-E', '-s', '-B', '-X', 'utf8', $module->entry]);
        } catch (ProcessTimedOutException) {
            throw new ModuleException(
                "Module '{$module->id}' took longer than {$module->timeout} s and was stopped.",
                "Moduuli ei valmistunut {$module->timeout} sekunnissa.",
            );
        }

        if (! $result->successful()) {
            $error = trim($result->errorOutput());

            // The reason shown to users is the error output's last line: a
            // module's own message, or the exception line of a traceback.
            throw new ModuleException(
                "Module '{$module->id}' failed with exit code {$result->exitCode()}: ".(Str::limit($error, 500) ?: 'no error output'),
                Str::limit(Str::afterLast($error, "\n"), 200) ?: 'Moduuli päättyi virheeseen.',
            );
        }

        try {
            // Decoded as objects only to check that the output is one.
            $isObject = json_decode($result->output(), flags: JSON_THROW_ON_ERROR) instanceof stdClass;
        } catch (JsonException) {
            $isObject = false;
        }

        if (! $isObject) {
            throw new ModuleException("Module '{$module->id}' must print a JSON object.", 'Moduuli palautti virheellisen vastauksen.');
        }

        return json_decode($result->output(), true);
    }

    /**
     * The variables the module gets. Symfony Process would pass on the
     * backend's whole environment, API keys included, so every inherited
     * variable is switched off (false) and only these few are set. A module
     * with "storage": true also gets its own data folder.
     *
     * @return array<string, string|false>
     */
    private function environment(PythonModule $module): array
    {
        $inherited = array_fill_keys(array_keys(getenv() + $_ENV), false);

        return [
            ...$inherited,
            'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
            'LANG' => 'C.UTF-8',
            ...($module->storage ? ['JALAOPS_DATA_DIR' => $this->dataDirectory($module)] : []),
        ];
    }

    /**
     * The module's data folder, made when it's first needed. The id is a
     * folder name (a-z, 0-9, -), so it can't point outside the storage folder.
     */
    public function dataDirectory(PythonModule $module): string
    {
        $directory = rtrim($this->storage, '/').'/'.$module->id;

        File::ensureDirectoryExists($directory);

        return $directory;
    }
}
