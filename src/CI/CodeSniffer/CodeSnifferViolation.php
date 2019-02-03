<?php declare(strict_types=1);

namespace SymfonyDocsBuilder\CI\CodeSniffer;

class CodeSnifferViolation
{
    private $file;
    private $code;
    private $reasons;

    public function __construct(string $file, string $code, array $reasons)
    {
        $this->file    = sprintf('/%s.rst', $file);
        $this->code    = $code;
        $this->reasons = $reasons;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getReasons(): array
    {
        return $this->reasons;
    }
}
