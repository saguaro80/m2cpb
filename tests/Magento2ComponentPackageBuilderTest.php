<?php

class Magento2ComponentPackageBuilderTest extends \PHPUnit\Framework\TestCase
{
    private $destinationZipPath;
    /**
     * @var Magento2ComponentPackageBuilder
     */
    private $builder;

    protected function setUp()
    {
        parent::setUp();
        $this->destinationZipPath = __DIR__ . '/fixtures/destination';
        $this->builder = new Magento2ComponentPackageBuilder(new DevNullOutput());
    }

    protected function tearDown()
    {
        parent::tearDown();
        @array_map([$this, 'cleanDir'], glob($this->destinationZipPath . '/*'));
    }

    private function cleanDir($target)
    {
        if (is_dir($target)) {
            $target = rtrim($target, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $files = glob($target . '*');
            foreach ($files as $file) {
                $this->cleanDir($file);
            }
            rmdir($target);
        } elseif (is_file($target)) {
            unlink($target);
        }
    }

    public function testBuildMarketplaceAndStandalonePackages()
    {
        $this->builder->build(
            __DIR__ . '/fixtures/awesome-module-repo/src',
            __DIR__ . '/fixtures/awesome-module-repo/composer.json',
            $this->destinationZipPath
        );
        $marketplacePackageZipPath = $this->destinationZipPath . '/awesome-module-1.1.2-marketplace.zip';
        $this->assertFileExists($marketplacePackageZipPath);
        $zip = new \ZipArchive();
        $zip->open($marketplacePackageZipPath);
        $zip->extractTo($this->destinationZipPath);
        $this->assertFileExists($this->destinationZipPath . '/composer.json');
        $this->assertFileExists($this->destinationZipPath . '/registration.php');
        $composerData = json_decode($this->destinationZipPath . '/composer.json', true);
        $this->assertEmpty($composerData['autoload']['psr-4']['Awesome\\Module\\']);

        $standalonePackageZipPath = $this->destinationZipPath . '/awesome-module-1.1.2-standalone.zip';
        $this->assertFileExists($standalonePackageZipPath);
        $zip = new \ZipArchive();
        $zip->open($standalonePackageZipPath);
        $zip->extractTo($this->destinationZipPath);
        $this->assertFileExists($this->destinationZipPath . '/app/code/Awesome/Module/registration.php');
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Source path "/invalid/path" is not a directory or is not readable.
     */
    public function testBuildMarkeplacePackageShouldFailIfSourcePathDoesNotExists()
    {
        $this->builder->build(
            '/invalid/path',
            __DIR__ . '/fixtures/awesome-module-repo/composer.json',
            $this->destinationZipPath
        );
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessageRegExp  /Cannot find Magento2 component registration file at path/
     */
    public function testBuildMarketplacePackageShouldFailIfSourcePathDoesNotContainRegistrationFile()
    {
        $this->builder->build(
            __DIR__ . '/fixtures/dir-without-registration-file',
            __DIR__ . '/fixtures/awesome-module-repo/composer.json',
            $this->destinationZipPath
        );
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessageRegExp  /Cannot decode source Composer file at path/
     */
    public function testBuildMarketplacePackageShouldFailIfSourceComposerIsNotValidJson()
    {
        $this->builder->build(
            __DIR__ . '/fixtures/awesome-module-repo/src',
            __DIR__ . '/fixtures/dir-with-invalid-composer-file/composer.json',
            $this->destinationZipPath
        );
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessageRegExp  /Cannot find required property "version" in source Composer file at path /
     */
    public function testBuildMarketplacePackageShouldFailIfSourceComposerDoesNotContainRequiredProperty()
    {
        $this->builder->build(
            __DIR__ . '/fixtures/awesome-module-repo/src',
            __DIR__ . '/fixtures/dir-with-incomplete-composer-file/composer.json',
            $this->destinationZipPath
        );
    }
}
