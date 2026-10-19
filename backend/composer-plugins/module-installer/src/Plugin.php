<?php

namespace JalaOps\ModuleInstaller;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

/**
 * Adds ModuleInstaller to Composer, so module packages are installed into
 * the backend's modules/packages folder.
 */
class Plugin implements PluginInterface
{
    public function activate(Composer $composer, IOInterface $io): void
    {
        $composer->getInstallationManager()->addInstaller(new ModuleInstaller($io, $composer));
    }

    public function deactivate(Composer $composer, IOInterface $io): void {}

    public function uninstall(Composer $composer, IOInterface $io): void {}
}
