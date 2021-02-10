<?php

namespace Richardhj\SymfonyUxSyncPlugin\Tests;

use PHPUnit\Framework\TestCase;
use Richardhj\SymfonyUxSyncPlugin\PackageJsonSynchronizer;
use Symfony\Component\Filesystem\Filesystem;

class PackageJsonSynchronizerTest extends TestCase
{
    private string $tempDir;
    private PackageJsonSynchronizer $synchronizer;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/flex-package-json-'.substr(md5(uniqid('', true)), 0, 6);
        (new Filesystem())->mirror(__DIR__.'/Fixtures/packageJson', $this->tempDir);

        $this->synchronizer = new PackageJsonSynchronizer($this->tempDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    public function testSynchronizeNoPackage()
    {
        $this->synchronizer->synchronize([]);

        // Should remove existing package references as it has been removed from the lock
        self::assertSame(
            [
                'name' => 'symfony/fixture',
                'devDependencies' => [
                    '@symfony/stimulus-bridge' => '^1.0.0',
                    'stimulus' => '^1.1.1',
                ],
            ],
            json_decode(file_get_contents($this->tempDir.'/package.json'), true, 512, JSON_THROW_ON_ERROR)
        );

        self::assertSame(
            [
                'controllers' => [],
                'entrypoints' => [],
            ],
            json_decode(file_get_contents($this->tempDir.'/assets/controllers.json'), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function testSynchronizeExistingPackage()
    {
        $this->synchronizer->synchronize(['symfony/existing-package']);

        // Should keep existing package references and config
        self::assertSame(
            [
                'name' => 'symfony/fixture',
                'devDependencies' => [
                    '@symfony/existing-package' => 'file:vendor/symfony/existing-package/Resources/assets',
                    '@symfony/stimulus-bridge' => '^1.0.0',
                    'stimulus' => '^1.1.1',
                ],
            ],
            json_decode(file_get_contents($this->tempDir.'/package.json'), true, 512, JSON_THROW_ON_ERROR)
        );

        self::assertSame(
            [
                'controllers' => [
                    '@symfony/existing-package' => [
                        'mock' => [
                            'enabled' => false,
                            'fetch' => 'eager',
                            'autoimport' => [
                                '@symfony/existing-package/dist/style.css' => false,
                                '@symfony/existing-package/dist/new-style.css' => true,
                            ],
                        ],
                    ],
                ],
                'entrypoints' => [],
            ],
            json_decode(file_get_contents($this->tempDir.'/assets/controllers.json'), true)
        );
    }

    public function testSynchronizeNewPackage()
    {
        $this->synchronizer->synchronize(['symfony/existing-package', 'symfony/new-package']);

        // Should keep existing package references and config and add the new package, while keeping the formatting
        self::assertSame(
            '{
   "name": "symfony/fixture",
   "devDependencies": {
      "@symfony/existing-package": "file:vendor/symfony/existing-package/Resources/assets",
      "@symfony/new-package": "file:vendor/symfony/new-package/assets",
      "@symfony/stimulus-bridge": "^1.0.0",
      "stimulus": "^1.1.1"
   }
}',
            file_get_contents($this->tempDir.'/package.json')
        );

        self::assertSame(
            [
                'controllers' => [
                    '@symfony/existing-package' => [
                        'mock' => [
                            'enabled' => false,
                            'fetch' => 'eager',
                            'autoimport' => [
                                '@symfony/existing-package/dist/style.css' => false,
                                '@symfony/existing-package/dist/new-style.css' => true,
                            ],
                        ],
                    ],
                    '@symfony/new-package' => [
                        'new' => [
                            'enabled' => true,
                            'fetch' => 'lazy',
                            'autoimport' => [
                                '@symfony/new-package/dist/style.css' => true,
                            ],
                        ],
                    ],
                ],
                'entrypoints' => ['admin.js'],
            ],
            json_decode(file_get_contents($this->tempDir.'/assets/controllers.json'), true, 512, JSON_THROW_ON_ERROR)
        );
    }
}
