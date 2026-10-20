<?php

namespace App\Services\Modules;

use App\Models\ModuleSetting;
use App\Models\ModuleWidgetSetting;
use FilesystemIterator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use PharData;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;
use ZipArchive;

/**
 * Adds and removes Python modules for modules:add and modules:remove. A
 * module comes from a folder, a git repository or an archive (.zip, .tar,
 * .tar.gz, .tgz or .gz, a file or a URL). It
 * is fetched into a temporary folder and checked with the same rules as an
 * installed module (ModuleRegistry), plus its files, its Python and its own
 * tests. Then a git repository that is a module package (a composer.json of
 * type jalaops-module) is added with Composer, pinned in composer.lock, and
 * anything else is copied into the modules folder. Both are changes to the
 * code, so they reach production through a pull request and the deploy.
 */
class ModuleManager
{
    private const ID_PATTERN = '/^[a-z0-9][a-z0-9-]*$/';

    // Folders in the modules folder that aren't modules: the installed
    // packages and the module SDK.
    private const RESERVED_IDS = ['packages', 'sdk'];

    // Upper limits for a module's files, so an archive can't fill the disk.
    private const MAX_FILES = 1000;

    private const MAX_BYTES = 20_000_000;

    // Folders and files that aren't checked or copied.
    private const SKIPPED = ['.git', '__pycache__', '.pytest_cache', '.mypy_cache', '.DS_Store'];

    // Seconds for git, a download, the module's tests and Composer.
    private const TIMEOUT = 300;

    // The archives a module can come in, by file name ending: PHP unpacks
    // .zip with ZipArchive and tar archives (also gzipped) with PharData.
    private const ARCHIVES = ['.zip' => 'zip', '.tar' => 'tar', '.tar.gz' => 'tar.gz', '.tgz' => 'tar.gz', '.gz' => 'tar.gz'];

    // A version tag of a module package, e.g. v1.2.0.
    private const VERSION_TAG = '/^v?(\d+)\.(\d+)\.(\d+)$/';

    public function __construct(private readonly ModuleRegistry $modules) {}

    /**
     * Fetches a module into a temporary folder: a local folder is copied, a
     * git URL is cloned (at ref, or a package's newest version tag), and an
     * archive (a file or a URL) is unpacked. With copy, a package is copied
     * into the modules folder like any other module instead of using Composer.
     *
     * @throws ModuleException when it can't be fetched
     */
    public function fetch(string $source, ?string $ref = null, bool $copy = false): ModuleSource
    {
        $remote = (bool) preg_match('#^https?://#i', $source);
        // A URL (is_dir() would also open file:// ones), or else a file or folder.
        $url = (bool) preg_match('#^[a-z][a-z0-9+.-]*://#i', $source) || (bool) preg_match('#^[\w.-]+@[\w.-]+:#', $source);
        $archive = $this->archiveType($remote ? (string) parse_url($source, PHP_URL_PATH) : $source);

        $kind = match (true) {
            ! $url && is_dir($source) => 'path',
            $archive !== null && ($remote || (! $url && is_file($source))) => 'archive',
            $url && (bool) preg_match('#^((https?|ssh|git|file)://|[\w.-]+@[\w.-]+:)#', $source) => 'git',
            default => throw new ModuleException("'{$source}' is not a folder, an archive (".implode(', ', array_keys(self::ARCHIVES)).') or a git URL.'),
        };

        if ($ref !== null && $kind !== 'git') {
            throw new ModuleException('--ref is only for git URLs.');
        }

        $temporary = sys_get_temp_dir().'/jalaops-module-'.Str::random(12);
        File::ensureDirectoryExists($temporary);

        try {
            $folder = "{$temporary}/source";

            if ($kind === 'path') {
                $this->copy(realpath($source), $folder);
            } elseif ($kind === 'archive') {
                $this->unpack($source, $remote, $archive, $temporary, $folder);
            } else {
                $this->cloneRepository($source, $ref, $folder);
            }

            $directory = $this->moduleRoot($folder);
            $package = $this->packageName($directory);

            // A package is installed at a version tag, so the code checked here
            // is the code Composer installs: without --ref, its newest one.
            if ($kind === 'git' && $package !== null && $ref === null) {
                $ref = $this->newestVersionTag($source);
                File::deleteDirectory($folder);
                $this->cloneRepository($source, $ref, $folder);
                $directory = $this->moduleRoot($folder);
            }
        } catch (Throwable $exception) {
            File::deleteDirectory($temporary);

            throw $exception;
        }

        return new ModuleSource($directory, $temporary, $kind, $source, $this->name($kind, $source, $directory, "{$temporary}/source"), $ref, $package, $copy);
    }

    /**
     * Checks a fetched module: its files, its module.json (as the app would
     * load it), its id and routes against the installed modules, that its
     * Python compiles, and its own tests (tests/, run without the backend's
     * environment, like the module itself). A package can have several modules.
     *
     * @return array{modules: array<string, PythonModule>, warnings: list<string>}
     *
     * @throws ModuleException when it isn't valid
     */
    public function check(ModuleSource $source, ?string $id = null, bool $force = false, bool $runTests = true): array
    {
        $python = $this->scan($source->directory);
        $modules = [];

        foreach ($this->moduleFolders($source, $id) as $moduleId => $directory) {
            try {
                $module = $this->modules->inspect($directory, $moduleId);
            } catch (ModuleException $exception) {
                throw new ModuleException("The module isn't valid: {$exception->getMessage()}");
            }

            $this->checkId($source, $moduleId, $force);
            $this->checkRoutes($module);
            $modules[$moduleId] = $module;
        }

        $this->compile($source->directory, $python);

        if ($runTests && is_dir("{$source->directory}/tests")) {
            $this->runTests($source->directory);
        }

        $warnings = [];

        foreach ($modules as $module) {
            if (is_file("{$module->directory}/requirements.txt")) {
                $warnings[] = "Module '{$module->id}' has a requirements.txt: add its libraries to backend/Dockerfile and docker/backend/Dockerfile, and rebuild the images.";
            }
        }

        if ($source->package !== null && ! $source->isPackage()) {
            $warnings[] = "It's the Composer package {$source->package}, copied without Composer, so composer.lock doesn't pin its version. From its git URL without --copy, it's added with Composer.";
        }

        return ['modules' => $modules, 'warnings' => $warnings];
    }

    /**
     * Adds a checked module: a package with Composer, anything else by
     * copying it into the modules folder.
     *
     * @param  array<string, PythonModule>  $modules  From check()
     * @return list<string> What changed
     *
     * @throws ModuleException when it can't be added
     */
    public function install(ModuleSource $source, array $modules, bool $force = false): array
    {
        if ($source->isPackage()) {
            return $this->requirePackage($source);
        }

        $module = reset($modules);
        $target = rtrim(config('modules.path'), '/')."/{$module->id}";

        if (is_dir($target)) {
            // check() allows this only with --force, for a module in the modules folder.
            if (! $force) {
                throw new ModuleException("{$target} already exists.");
            }

            File::deleteDirectory($target);
        }

        $this->copy($source->directory, $target);
        $this->giveToOwnerOf(config('modules.path'), $target);

        return ["Added module '{$module->id}' ({$module->name}) to ".$this->relative($target).'.'];
    }

    /**
     * Deletes the temporary folder of a fetched module.
     */
    public function cleanup(ModuleSource $source): void
    {
        if ($source->temporary !== null) {
            File::deleteDirectory($source->temporary);
        }
    }

    /**
     * Creates a new module in the modules folder from the stubs in
     * stubs/module: a module.json, a main.py whose class uses the module SDK
     * (weather-station → WeatherStationModule) and its tests.
     *
     * @return list<string> The files created
     *
     * @throws ModuleException when the id isn't valid or is taken
     */
    public function create(string $id, ?string $name = null): array
    {
        if (! preg_match(self::ID_PATTERN, $id)) {
            throw new ModuleException("'{$id}' can't be a module id (a-z, 0-9 and -).");
        }

        if (in_array($id, self::RESERVED_IDS, true)) {
            throw new ModuleException("'{$id}' is a folder of the modules folder, not a module id.");
        }

        $target = rtrim(config('modules.path'), '/')."/{$id}";

        if ($this->modules->locate($id) !== null || file_exists($target)) {
            throw new ModuleException("A module with the id '{$id}' already exists.");
        }

        // A Python class name can't start with a digit.
        $class = Str::studly($id).'Module';
        $class = ctype_digit($class[0]) ? "_{$class}" : $class;
        $values = [
            '{{ id }}' => $id,
            '{{ name }}' => $name ?? Str::ucfirst(str_replace('-', ' ', $id)),
            '{{ class }}' => $class,
        ];

        $created = [];

        foreach (File::allFiles(base_path('stubs/module')) as $stub) {
            $file = "{$target}/".Str::beforeLast($stub->getRelativePathname(), '.stub');
            // In module.json the name is inside a JSON string.
            $content = strtr($stub->getContents(), basename($file) === 'module.json'
                ? ['{{ name }}' => substr(json_encode($values['{{ name }}'], JSON_UNESCAPED_UNICODE), 1, -1)] + $values
                : $values);

            File::ensureDirectoryExists(dirname($file));
            File::put($file, $content);
            $created[] = $this->relative($file);
        }

        $this->giveToOwnerOf(config('modules.path'), $target);
        sort($created);

        return $created;
    }

    /**
     * Removes an installed module: a package with Composer (and its
     * repository in composer.json), a folder by deleting it. With purge, also
     * its data folder and its settings in the database.
     *
     * @return list<string> What changed
     *
     * @throws ModuleException when there's no such module or it can't be removed
     */
    public function remove(string $id, bool $purge = false): array
    {
        $location = $this->modules->locate($id)
            ?? throw new ModuleException("Module '{$id}' was not found. modules:list lists them.");

        $done = [];

        if ($location['package'] !== null) {
            $this->composer(['remove', $location['package']], 'composer remove');
            $done[] = "Removed the package {$location['package']} (composer.json and composer.lock changed).";

            $repository = "jalaops-module-{$id}";
            if ($this->hasRepository($repository)) {
                $this->composer(['config', '--unset', "repositories.{$repository}"], 'composer config');
                $done[] = "Removed the repository {$repository} from composer.json.";
            }
        } else {
            $directory = realpath($location['directory']);
            $modulesPath = realpath(config('modules.path'));

            // Only a module's own folder inside the modules folder, never packages.
            if ($directory === false || $modulesPath === false || dirname($directory) !== $modulesPath || basename($directory) === 'packages') {
                throw new ModuleException("Module '{$id}' is not in the modules folder, so it can't be removed here.");
            }

            File::deleteDirectory($directory);
            $done[] = 'Deleted '.$this->relative($directory).'.';
        }

        if ($purge) {
            $data = rtrim(config('modules.storage'), '/')."/{$id}";

            if (is_dir($data)) {
                File::deleteDirectory($data);
                $done[] = "Deleted its data folder {$data}.";
            }

            ModuleSetting::query()->whereKey($id)->delete();
            ModuleWidgetSetting::query()->where('widget', 'like', "{$id}.%")->delete();
            $done[] = 'Deleted its settings (on/off and widget places).';
        }

        return $done;
    }

    /**
     * The folder with the module.json, or a package's root (a package can be
     * a folder of modules). An archive often has everything in one folder.
     */
    private function moduleRoot(string $directory): string
    {
        if (is_file("{$directory}/module.json") || $this->packageName($directory) !== null) {
            return $directory;
        }

        $entries = array_values(array_diff(scandir($directory) ?: [], ['.', '..', ...self::SKIPPED]));

        if (count($entries) === 1 && is_dir("{$directory}/{$entries[0]}") && ! is_link("{$directory}/{$entries[0]}")) {
            return $this->moduleRoot("{$directory}/{$entries[0]}");
        }

        throw new ModuleException('There is no module.json in the source.');
    }

    /**
     * The Composer package name of a folder with a composer.json of type
     * jalaops-module, or null.
     */
    private function packageName(string $directory): ?string
    {
        $composer = is_file("{$directory}/composer.json") ? json_decode((string) file_get_contents("{$directory}/composer.json"), true) : null;

        return is_array($composer) && ($composer['type'] ?? null) === ModuleRegistry::PACKAGE_TYPE && is_string($composer['name'] ?? null)
            ? $composer['name']
            : null;
    }

    /**
     * A name for the module from where it came from, for its id: the
     * folder's, the repository's or the archive's.
     */
    private function name(string $kind, string $source, string $directory, string $copy): string
    {
        $file = basename(preg_replace('#[?\#].*$#', '', str_replace(':', '/', $source)));

        return match (true) {
            $kind === 'path' => basename(realpath($source)),
            $directory !== $copy => basename($directory),
            $kind === 'archive' => substr($file, 0, -strlen($this->archiveEnding($file))),
            default => preg_replace('#\.git$#i', '', $file),
        };
    }

    /**
     * The modules to add, by id: a package's are named by the package
     * (jalaops/weather is "weather") or its folders; anything else by --id,
     * its composer.json or its folder (jalaops-module-weather is "weather").
     *
     * @return array<string, string> id => folder
     */
    private function moduleFolders(ModuleSource $source, ?string $id): array
    {
        $hasManifest = is_file("{$source->directory}/module.json");

        if ($source->isPackage()) {
            if ($id !== null) {
                throw new ModuleException("--id can't be used for a package: its modules are named by the package ({$source->package}).");
            }

            if ($hasManifest) {
                return [Str::afterLast($source->package, '/') => $source->directory];
            }

            $folders = [];
            foreach (glob("{$source->directory}/*/module.json") ?: [] as $manifest) {
                $folders[basename(dirname($manifest))] = dirname($manifest);
            }

            return $folders ?: throw new ModuleException("The package {$source->package} has no module.json.");
        }

        if (! $hasManifest) {
            throw new ModuleException('It is a package with several modules: add it from its git URL, without --copy.');
        }

        $id ??= Str::of($source->package !== null ? Str::afterLast($source->package, '/') : $source->name)
            ->lower()
            ->replaceStart('jalaops-module-', '')
            ->toString();

        if (! preg_match(self::ID_PATTERN, $id)) {
            throw new ModuleException("'{$id}' can't be a module id (a-z, 0-9 and -): give one with --id.");
        }

        if (in_array($id, self::RESERVED_IDS, true)) {
            throw new ModuleException("'{$id}' is a folder of the modules folder, not a module id: give another one with --id.");
        }

        return [$id => $source->directory];
    }

    /**
     * An id that's taken can be replaced with --force, for a module in the
     * modules folder; a package can always be updated to another version.
     */
    private function checkId(ModuleSource $source, string $id, bool $force): void
    {
        $installed = $this->modules->locate($id);

        if ($installed === null || ($source->isPackage() && $installed['package'] === $source->package)) {
            return;
        }

        if ($installed['package'] !== null || $source->isPackage()) {
            $installedAs = $installed['package'] !== null ? "the package {$installed['package']}" : 'a folder module';

            throw new ModuleException("A module with the id '{$id}' is already installed as {$installedAs} ({$this->relative($installed['directory'])}). Remove it first with modules:remove {$id}, or give this one another id with --id.");
        }

        if (! $force) {
            throw new ModuleException("A module with the id '{$id}' is already installed ({$this->relative($installed['directory'])}). --force replaces it.");
        }
    }

    /**
     * A module's routes can't use the first parts of another module's
     * addresses: the installed one would keep them.
     */
    private function checkRoutes(PythonModule $module): void
    {
        $firsts = array_unique(array_map(fn (array $route) => explode('/', $route['path'])[1], $module->routes));

        foreach ($this->modules->all() as $other) {
            if ($other->id === $module->id) {
                continue;
            }

            foreach ($other->routes as $route) {
                $first = explode('/', $route['path'])[1];

                if (in_array($first, $firsts, true)) {
                    throw new ModuleException("The address /{$first} is already module '{$other->id}''s, so the routes of '{$module->id}' wouldn't work.");
                }
            }
        }
    }

    /**
     * Checks a module's files: no links (they could point anywhere), and at
     * most MAX_FILES files and MAX_BYTES. Returns its Python files.
     *
     * @return list<string> Paths relative to the folder
     */
    private function scan(string $directory): array
    {
        $files = 0;
        $bytes = 0;
        $python = [];

        foreach ($this->files($directory) as $file) {
            if ($file->isLink()) {
                throw new ModuleException('The module has a symbolic link ('.$this->inside($directory, $file).'); modules can only have plain files.');
            }

            if ($file->isFile()) {
                $files++;
                $bytes += $file->getSize();

                if ($file->getExtension() === 'py') {
                    $python[] = $this->inside($directory, $file);
                }
            }
        }

        if ($files > self::MAX_FILES || $bytes > self::MAX_BYTES) {
            throw new ModuleException('The module is too big: at most '.self::MAX_FILES.' files and '.(self::MAX_BYTES / 1_000_000).' MB.');
        }

        return $python;
    }

    /**
     * The files and folders inside a folder, without SKIPPED ones and
     * without following links.
     *
     * @return iterable<SplFileInfo>
     */
    private function files(string $directory): iterable
    {
        $iterator = new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS);
        $filter = new RecursiveCallbackFilterIterator($iterator, fn (SplFileInfo $file) => ! in_array($file->getFilename(), self::SKIPPED, true) && $file->getExtension() !== 'pyc');

        return new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST);
    }

    /**
     * Copies a module's files (not SKIPPED ones), refusing links.
     */
    private function copy(string $from, string $to): void
    {
        File::ensureDirectoryExists($to);

        foreach ($this->files($from) as $file) {
            $target = $to.'/'.$this->inside($from, $file);

            if ($file->isLink()) {
                throw new ModuleException('The module has a symbolic link ('.$this->inside($from, $file).'); modules can only have plain files.');
            }

            $file->isDir() ? File::ensureDirectoryExists($target) : File::copy($file->getPathname(), $target);
        }
    }

    /**
     * The Python files compile: a syntax error is found before the module
     * runs. Nothing is run or written.
     *
     * @param  list<string>  $files
     */
    private function compile(string $directory, array $files): void
    {
        if ($files === []) {
            return;
        }

        $script = <<<'PY'
            import sys
            for path in sys.stdin.read().splitlines():
                try:
                    with open(path, encoding="utf-8") as file:
                        compile(file.read(), path, "exec")
                except (SyntaxError, UnicodeDecodeError, ValueError) as error:
                    print(f"{path}:{getattr(error, 'lineno', '?')}: {getattr(error, 'msg', error)}", file=sys.stderr)
                    sys.exit(1)
            PY;

        $result = Process::path($directory)->env($this->moduleEnvironment())->timeout(60)
            ->input(implode("\n", $files))
            ->run([config('modules.python'), '-c', $script]);

        if (! $result->successful()) {
            throw new ModuleException('The module\'s Python doesn\'t compile: '.trim($result->errorOutput()));
        }
    }

    /**
     * Runs the module's own tests (python3 -m unittest in its folder).
     */
    private function runTests(string $directory): void
    {
        $result = Process::path($directory)->env($this->moduleEnvironment())->timeout(self::TIMEOUT)
            ->run([config('modules.python'), '-m', 'unittest']);

        if (! $result->successful()) {
            $output = trim($result->errorOutput() ?: $result->output());

            throw new ModuleException("The module's own tests failed (--skip-tests skips them):\n".Str::of($output)->explode("\n")->take(-15)->implode("\n"));
        }
    }

    /**
     * The environment for a module's Python: no inherited variables, so it
     * never sees the backend's keys, the module SDK on PYTHONPATH, and no
     * .pyc files (the same as ModuleRunner with -B), which would be left in
     * the SDK's folder, owned by the container's root.
     *
     * @return array<string, string|false>
     */
    private function moduleEnvironment(): array
    {
        return [
            ...array_fill_keys(array_keys(getenv() + $_ENV), false),
            'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
            'LANG' => 'C.UTF-8',
            'PYTHONPATH' => config('modules.sdk'),
            'PYTHONDONTWRITEBYTECODE' => '1',
        ];
    }

    /**
     * Clones a repository, only its newest commit (at ref, a tag or a branch).
     */
    private function cloneRepository(string $url, ?string $ref, string $target): void
    {
        $result = Process::timeout(self::TIMEOUT)->env(['GIT_TERMINAL_PROMPT' => '0'])
            ->run(['git', 'clone', '--quiet', '--depth', '1', ...($ref !== null ? ['--branch', $ref] : []), '--', $url, $target]);

        if (! $result->successful()) {
            throw new ModuleException("git clone of {$url} failed: ".$this->lastLine($result->errorOutput()));
        }
    }

    /**
     * A package repository's newest version tag (v1.2.0 or 1.2.0).
     */
    private function newestVersionTag(string $url): string
    {
        $result = Process::timeout(self::TIMEOUT)->env(['GIT_TERMINAL_PROMPT' => '0'])
            ->run(['git', 'ls-remote', '--tags', '--refs', '--', $url]);

        $tags = array_values(array_filter(
            array_map(fn (string $line) => Str::afterLast(trim($line), 'refs/tags/'), explode("\n", trim($result->output()))),
            fn (string $tag) => (bool) preg_match(self::VERSION_TAG, $tag),
        ));

        if (! $result->successful() || $tags === []) {
            throw new ModuleException('A module package is installed at a version tag (e.g. v1.0.0), and the repository has none. Tag a version first.');
        }

        usort($tags, fn (string $a, string $b) => version_compare(ltrim($b, 'v'), ltrim($a, 'v')));

        return $tags[0];
    }

    /**
     * The kind of archive a file name is (zip, tar or tar.gz), or null.
     */
    private function archiveType(string $name): ?string
    {
        $ending = $this->archiveEnding($name);

        return $ending === null ? null : self::ARCHIVES[$ending];
    }

    private function archiveEnding(string $name): ?string
    {
        $name = strtolower($name);

        // The longest ending first: .tar.gz before .gz.
        foreach (['.tar.gz', '.tgz', '.zip', '.tar', '.gz'] as $ending) {
            if (str_ends_with($name, $ending)) {
                return $ending;
            }
        }

        return null;
    }

    /**
     * Unpacks an archive (downloaded first if it's a URL), checking its
     * paths first so nothing lands outside the folder.
     */
    private function unpack(string $source, bool $remote, string $type, string $temporary, string $target): void
    {
        // PharData tells a tar archive's compression by the file name.
        $file = $temporary.'/archive.'.$type;

        if ($remote) {
            try {
                $response = Http::timeout(self::TIMEOUT)->get($source);
            } catch (Throwable $exception) {
                throw new ModuleException("Downloading {$source} failed: {$exception->getMessage()}");
            }

            if (! $response->successful()) {
                throw new ModuleException("Downloading {$source} failed with status {$response->status()}.");
            }

            file_put_contents($file, $response->body());
        } else {
            File::copy($source, $file);
        }

        if (filesize($file) > self::MAX_BYTES) {
            throw new ModuleException('The archive is too big: at most '.(self::MAX_BYTES / 1_000_000).' MB.');
        }

        File::ensureDirectoryExists($target);

        $type === 'zip' ? $this->unzip($file, $target) : $this->untar($file, $target);
    }

    private function unzip(string $file, string $target): void
    {
        $zip = new ZipArchive;

        if ($zip->open($file) !== true) {
            throw new ModuleException('The archive is not a valid .zip file.');
        }

        $bytes = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $this->checkArchivePath((string) $zip->getNameIndex($index));
            $bytes += $zip->statIndex($index)['size'] ?? 0;

            // A Unix symbolic link, in the entry's file mode.
            if ($zip->getExternalAttributesIndex($index, $system, $attributes) && $system === ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000) {
                throw new ModuleException('The archive has a symbolic link; modules can only have plain files.');
            }
        }

        if ($zip->numFiles > self::MAX_FILES || $bytes > self::MAX_BYTES) {
            throw new ModuleException('The module is too big: at most '.self::MAX_FILES.' files and '.(self::MAX_BYTES / 1_000_000).' MB.');
        }

        $zip->extractTo($target);
        $zip->close();
    }

    private function untar(string $file, string $target): void
    {
        $this->checkTarEntries($file);

        try {
            (new PharData($file))->extractTo($target, null, true);
        } catch (Throwable) {
            throw new ModuleException('The archive is not a valid tar archive (.tar, .tar.gz, .tgz or .gz).');
        }
    }

    /**
     * Reads a tar archive's headers (gzopen also reads an uncompressed file)
     * before anything is unpacked: only files and folders with safe paths,
     * no links, and at most MAX_FILES files and MAX_BYTES unpacked.
     */
    private function checkTarEntries(string $file): void
    {
        $tar = gzopen($file, 'rb') ?: throw new ModuleException('The archive is not a valid tar archive.');
        $files = 0;
        $bytes = 0;
        $longName = null;

        try {
            while (strlen($header = (string) gzread($tar, 512)) === 512 && trim($header, "\0") !== '') {
                if (substr($header, 257, 5) !== 'ustar' && ! ctype_digit(trim(substr($header, 124, 12), " \0"))) {
                    throw new ModuleException('The archive is not a valid tar archive.');
                }

                $size = (int) octdec(trim(substr($header, 124, 12), " \0"));
                $type = $header[156];
                $data = $size > 0 ? (string) gzread($tar, (int) ceil($size / 512) * 512) : '';

                // GNU long names and pax headers describe the next entry.
                if ($type === 'L') {
                    $longName = rtrim(substr($data, 0, $size), "\0");

                    continue;
                }

                if (in_array($type, ['x', 'g'], true)) {
                    continue;
                }

                if (! in_array($type, ['0', "\0", '5'], true)) {
                    throw new ModuleException('The archive has a link or a special file; modules can only have plain files.');
                }

                $prefix = rtrim(substr($header, 345, 155), "\0");
                $name = $longName ?? ltrim($prefix.'/'.rtrim(substr($header, 0, 100), "\0"), '/');
                $longName = null;
                $this->checkArchivePath(rtrim(preg_replace('#^\./#', '', $name), '/') ?: '.');

                $files++;
                $bytes += $size;

                if ($files > self::MAX_FILES || $bytes > self::MAX_BYTES) {
                    throw new ModuleException('The module is too big: at most '.self::MAX_FILES.' files and '.(self::MAX_BYTES / 1_000_000).' MB.');
                }
            }
        } finally {
            gzclose($tar);
        }
    }

    /**
     * A path inside an archive can't be absolute or go up (../), which would
     * write outside the folder.
     */
    private function checkArchivePath(string $path): void
    {
        if ($path === '' || str_contains($path, '\\') || str_starts_with($path, '/') || in_array('..', explode('/', $path), true)) {
            throw new ModuleException("The archive has an unsafe path: {$path}");
        }
    }

    /**
     * Adds a module package with Composer at the version checked: its git
     * repository in composer.json, and the package in composer.json and
     * composer.lock. The repository is removed again if Composer fails.
     *
     * @return list<string>
     */
    private function requirePackage(ModuleSource $source): array
    {
        if (! preg_match(self::VERSION_TAG, (string) $source->ref, $version)) {
            throw new ModuleException('A module package is installed at a version tag: use --ref with a tag such as v1.2.0.');
        }

        $repository = Str::of($source->name)->lower()->replaceMatches('/[^a-z0-9-]+/', '-')->trim('-')->toString();
        $added = ! $this->hasRepository($repository);
        $done = [];

        if ($added) {
            $this->composer(['config', "repositories.{$repository}", 'vcs', $source->source], 'composer config');
            $done[] = "Added the repository {$repository} to composer.json.";
        }

        $constraint = "^{$version[1]}.{$version[2]}";

        try {
            $this->composer(['require', "{$source->package}:{$constraint}"], 'composer require');
        } catch (ModuleException $exception) {
            if ($added) {
                $this->composer(['config', '--unset', "repositories.{$repository}"], 'composer config');
            }

            throw $exception;
        }

        $done[] = "Added the package {$source->package}:{$constraint} at {$source->ref} (composer.json and composer.lock changed).";

        return $done;
    }

    /**
     * Whether composer.json has a repository with this name.
     */
    private function hasRepository(string $name): bool
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);
        $repositories = $composer['repositories'] ?? [];

        return array_key_exists($name, $repositories) || in_array($name, array_column($repositories, 'name'), true);
    }

    /**
     * @param  list<string>  $arguments
     */
    private function composer(array $arguments, string $what): void
    {
        $result = Process::path(base_path())->timeout(self::TIMEOUT)->run(['composer', ...$arguments, '--no-interaction']);

        if (! $result->successful()) {
            throw new ModuleException("{$what} failed: ".$this->lastLine($result->errorOutput() ?: $result->output()));
        }
    }

    /**
     * Files copied by root (in the Docker container) belong to the owner of
     * the modules folder, so the developer can edit them on the host.
     */
    private function giveToOwnerOf(string $reference, string $directory): void
    {
        if (! function_exists('posix_getuid') || posix_getuid() !== 0 || ($owner = fileowner($reference)) === 0 || $owner === false) {
            return;
        }

        $group = filegroup($reference);

        foreach ([new SplFileInfo($directory), ...$this->files($directory)] as $file) {
            @chown($file->getPathname(), $owner);
            @chgrp($file->getPathname(), $group);
        }
    }

    private function inside(string $directory, SplFileInfo $file): string
    {
        return ltrim(Str::after($file->getPathname(), $directory), '/');
    }

    private function relative(string $path): string
    {
        $path = realpath($path) ?: $path;

        return str_starts_with($path, base_path().'/') ? substr($path, strlen(base_path()) + 1) : $path;
    }

    private function lastLine(string $output): string
    {
        return Str::of(trim($output))->explode("\n")->filter()->last() ?? 'no output';
    }
}
