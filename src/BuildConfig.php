<?php

/*
 * This file is part of the Docs Builder package.
 * (c) Ryan Weaver <ryan@symfonycasts.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyDocsBuilder;

use Doctrine\RST\Configuration;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class BuildConfig
{
    private const PHP_DOC_URL = 'https://secure.php.net/manual/en';
    private const SYMFONY_API_URL = 'https://api.symfony.com/{symfonyVersion}';
    private const SYMFONY_DOC_URL = 'https://symfony.com/doc/{symfonyVersion}';

    private $useBuildCache;
    private $theme;
    private $symfonyVersion;
    private $contentDir;
    private $outputDir;
    private $cacheDir;
    private $imagesDir;
    private $imagesPublicPrefix;
    private $subdirectoryToBuild;
    private $excludedPaths;
    private $fileFinder;
    private $isContentAString;
    private $disableJsonFileGeneration;

    public function __construct()
    {
        $this->useBuildCache = true;
        $this->theme = Configuration::THEME_DEFAULT;
        $this->symfonyVersion = '4.4';
        $this->excludedPaths = [];
        $this->imagesPublicPrefix = '';
        $this->isContentAString = false;
        $this->disableJsonFileGeneration = false;
    }

    public function createFileFinder(): Finder
    {
        if (null === $this->fileFinder) {
            $this->fileFinder = new Finder();
            $this->fileFinder
                ->in($this->getContentDir())
                // TODO - read this from the rst-parser Configuration
                ->name('*.rst')
                ->notName('*.rst.inc')
                ->files()
                ->exclude($this->excludedPaths);
        }

        // clone to get a fresh instance and not share state
        return clone $this->fileFinder;
    }

    public function getTheme(): string
    {
        return $this->theme;
    }

    public function getSymfonyVersion(): string
    {
        return $this->symfonyVersion;
    }

    public function getPhpDocUrl(): string
    {
        return self::PHP_DOC_URL;
    }

    public function getSymfonyApiUrl(): string
    {
        return str_replace('{symfonyVersion}', $this->getSymfonyVersion(), self::SYMFONY_API_URL);
    }

    public function getSymfonyDocUrl(): string
    {
        return str_replace('{symfonyVersion}', $this->getSymfonyVersion(), self::SYMFONY_DOC_URL);
    }

    public function disableBuildCache(): self
    {
        $this->useBuildCache = false;

        return $this;
    }

    public function isBuildCacheEnabled(): bool
    {
        return $this->useBuildCache;
    }

    public function getContentDir(): string
    {
        if (null === $this->contentDir) {
            throw new \InvalidArgumentException('RST contents directory is not defined. Set it with the setContentDir() method.');
        }

        return $this->contentDir;
    }

    public function getSubdirectoryToBuild(): ?string
    {
        return $this->subdirectoryToBuild;
    }

    public function getOutputDir(): string
    {
        return $this->outputDir ?: $this->getContentDir().'/output';
    }

    public function getCacheDir(): string
    {
        return $this->cacheDir ?: $this->getOutputDir().'/.cache';
    }

    public function getImagesDir(): string
    {
        return $this->imagesDir ?: $this->getOutputDir().'/_images';
    }

    public function getImagesPublicPrefix(): string
    {
        return $this->imagesPublicPrefix;
    }

    public function generateJsonFiles(): bool
    {
        return !$this->disableJsonFileGeneration;
    }

    public function isContentAString(): bool
    {
        return $this->isContentAString;
    }

    public function setSymfonyVersion(string $version): self
    {
        $this->symfonyVersion = $version;

        return $this;
    }

    public function setTheme(string $theme): self
    {
        $this->theme = $theme;

        return $this;
    }

    public function setContentDir(string $contentDir): self
    {
        if (!file_exists($contentDir)) {
            throw new \InvalidArgumentException(sprintf('RST contents directory "%s" does not exist', $contentDir));
        }

        $this->contentDir = rtrim(realpath($contentDir), DIRECTORY_SEPARATOR);

        return $this;
    }

    public function setSubdirectoryToBuild(string $contentSubDir): self
    {
        $this->subdirectoryToBuild = trim($contentSubDir, DIRECTORY_SEPARATOR);

        return $this;
    }

    public function setOutputDir(string $outputDir): self
    {
        (new Filesystem())->mkdir($outputDir);
        if (!file_exists($outputDir)) {
            throw new \InvalidArgumentException(sprintf('Doc builder output directory "%s" does not exist', $outputDir));
        }

        $this->outputDir = rtrim(realpath($outputDir), DIRECTORY_SEPARATOR);

        return $this;
    }

    public function setCacheDir(string $cacheDir): self
    {
        (new Filesystem())->mkdir($cacheDir);
        if (!file_exists($cacheDir)) {
            throw new \InvalidArgumentException(sprintf('Doc builder cache directory "%s" does not exist', $cacheDir));
        }

        $this->cacheDir = rtrim(realpath($cacheDir), DIRECTORY_SEPARATOR);

        return $this;
    }

    /**
     * The directory where the images will be copied to. E.g. use this to
     * copy them into the public/ directory of a Symfony application.
     */
    public function setImagesDir(string $imagesDir): self
    {
        (new Filesystem())->mkdir($imagesDir);
        if (!file_exists($imagesDir)) {
            throw new \InvalidArgumentException(sprintf('Doc builder images directory "%s" does not exist', $imagesDir));
        }

        $this->imagesDir = rtrim(realpath($imagesDir), DIRECTORY_SEPARATOR);

        return $this;
    }

    /**
     * The string prefixes to the `src` attribute of `<img>` tags. This is useful when
     * publishing images in a public directory of a Symfony application.
     */
    public function setImagesPublicPrefix(string $prefix): self
    {
        $this->imagesPublicPrefix = $prefix;

        return $this;
    }

    public function setExcludedPaths(array $excludedPaths)
    {
        if (null !== $this->fileFinder) {
            throw new \LogicException('setExcludePaths() cannot be called after getFileFinder() (because the Finder has been initialized).');
        }

        $this->excludedPaths = $excludedPaths;
    }

    // needed to differentiate between building a dir of contents or just a string of contents
    public function setIsContentAString(bool $isString): self
    {
        $this->isContentAString = true;

        return $this;
    }

    public function disableJsonFileGeneration(): self
    {
        $this->disableJsonFileGeneration = true;

        return $this;
    }
}
