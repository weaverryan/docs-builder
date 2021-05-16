<?php

namespace SymfonyDocsBuilder;

use Doctrine\RST\Builder;
use Doctrine\RST\Meta\Metas;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Filesystem\Filesystem;
use SymfonyDocsBuilder\BuildConfig;
use SymfonyDocsBuilder\CI\MissingFilesChecker;
use SymfonyDocsBuilder\ConfigFileParser;
use SymfonyDocsBuilder\Generator\HtmlForPdfGenerator;
use SymfonyDocsBuilder\Generator\JsonGenerator;
use SymfonyDocsBuilder\KernelFactory;

class BuildResult
{
    private $builder;
    private $errors;
    private $jsonResults = [];

    public function __construct(Builder $builder)
    {
        $this->builder = $builder;
        $this->errors = [];
        foreach ($builder->getErrorManager()->getErrors() as $error) {
            $this->errors[] = $error->asString();
        }
    }

    public function appendError(string $errorMessage): void
    {
        $this->errors[] = $errorMessage;
    }

    public function prependError(string $errorMessage): void
    {
        $this->errors = array_merge([$errorMessage], $this->errors);
    }

    public function isSuccessful(): bool
    {
        return null === $this->errors || 0 === \count($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getMetadata(): Metas
    {
        return $this->builder->getMetas();
    }

    /**
     * Returns the "master document": the first file whose toctree is parsed.
     *
     * Unless customized, this is "index" (i.e. file index.rst).
     */
    public function getMasterDocumentFilename(): string
    {
        return $this->builder->getIndexName();
    }

    /**
     * Returns the JSON array data generated for each file, keyed by the source filename.
     */
    public function getJsonResults(): array
    {
        return $this->jsonResults;
    }

    public function setJsonResults(array $jsonResults): void
    {
        $this->jsonResults = $jsonResults;
    }
}
