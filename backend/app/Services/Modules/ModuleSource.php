<?php

namespace App\Services\Modules;

/**
 * A module fetched by ModuleManager for modules:add: the folder it's in (a
 * temporary copy), where it came from, and the Composer package it is, if any.
 */
final class ModuleSource
{
    /**
     * @param  string  $directory  The module's folder (module.json at its root), or a package's root
     * @param  string|null  $temporary  The temporary folder to delete afterwards
     * @param  'path'|'git'|'archive'  $kind
     * @param  string  $source  The folder, git URL or archive it was fetched from
     * @param  string  $name  A name from the source (its folder, repository or archive), for the module's id
     * @param  string|null  $ref  The git tag or branch it was cloned at
     * @param  string|null  $package  The Composer package name of a jalaops-module package, e.g. jalaops/weather
     */
    public function __construct(
        public readonly string $directory,
        public readonly ?string $temporary,
        public readonly string $kind,
        public readonly string $source,
        public readonly string $name,
        public readonly ?string $ref = null,
        public readonly ?string $package = null,
    ) {}

    /**
     * Whether it's installed with Composer: a git repository that is a module package.
     */
    public function isPackage(): bool
    {
        return $this->kind === 'git' && $this->package !== null;
    }
}
