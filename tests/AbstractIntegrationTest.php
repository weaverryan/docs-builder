<?php

namespace SymfonyDocsBuilder\Tests;

use Doctrine\RST\Builder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use SymfonyDocsBuilder\BuildConfig;

abstract class AbstractIntegrationTest extends TestCase
{
    protected function createBuildConfig(string $sourceDir): BuildConfig
    {
        $filesystem = new Filesystem();
        $filesystem->remove(__DIR__.'/_output');

        return (new BuildConfig())
            ->setSymfonyVersion('4.0')
            ->setContentDir($sourceDir)
            ->disableBuildCache()
            ->setOutputDir(__DIR__.'/_output')
        ;
    }
}
