<?php

namespace Richardhj\SymfonyUxSyncPlugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

/**
 * @internal
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
    private Composer $composer;
    private IOInterface $io;

    private static bool $activated = true;
    private static array $options = [];

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;

        $extra = $this->composer->getPackage()->getExtra();

        self::$options = array_merge(
            [
                'root-dir' => $extra['symfony']['root-dir'] ?? '.',
            ],
            $extra
        );
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
        self::$activated = false;
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
    }

    public function install(Event $event = null): void
    {
        $this->synchronizePackageJson(self::$options['root-dir']);
    }

    private function synchronizePackageJson(?string $rootDir): void
    {
        $synchronizer = new PackageJsonSynchronizer($rootDir);

        if ($synchronizer->shouldSynchronize()) {
            $packagesNames = array_column($this->composer->getLocker()->getLockData()['packages'] ?? [], 'name');

            $this->io->writeError('<info>Synchronizing package.json with PHP packages</>');
            $synchronizer->synchronize($packagesNames);
            $this->io->writeError('Don\'t forget to run <comment>npm install --force</> or <comment>yarn install --force</> to refresh your JavaScript dependencies!');
        }
    }

    public static function getSubscribedEvents(): array
    {
        if (!self::$activated) {
            return [];
        }

        return [
            ScriptEvents::POST_INSTALL_CMD => 'install',
        ];
    }
}
