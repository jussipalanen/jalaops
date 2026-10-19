<?php

namespace JalaOps\ModuleInstaller;

use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\PartialComposer;

/**
 * Installs packages of type jalaops-module into modules/packages/<name>,
 * next to the app's own modules, instead of vendor/. The name is the
 * package name after the slash (jalaops/worktime goes to
 * modules/packages/worktime). The folder is ignored by git: the version
 * comes from composer.lock, like any other package.
 */
class ModuleInstaller extends LibraryInstaller
{
    public const TYPE = 'jalaops-module';

    public function __construct(IOInterface $io, PartialComposer $composer)
    {
        parent::__construct($io, $composer, self::TYPE);
    }

    public function getInstallPath(PackageInterface $package): string
    {
        $name = substr($package->getPrettyName(), strrpos($package->getPrettyName(), '/') + 1);

        // The vendor folder's parent is the backend's root.
        return dirname($this->vendorDir).'/modules/packages/'.$name;
    }
}
