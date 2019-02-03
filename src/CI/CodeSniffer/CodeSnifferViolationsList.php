<?php declare(strict_types=1);

namespace SymfonyDocsBuilder\CI\CodeSniffer;

class CodeSnifferViolationsList implements \Countable
{
    private $violations = [];

    public function add(string $file, string $code, array $reasons)
    {
        $this->violations[] = new CodeSnifferViolation($file, $code, $reasons);
    }

    public function count(): int
    {
        return \count($this->violations);
    }

    public function getViolationsAsArray(): array
    {
        return array_map(
            function (CodeSnifferViolation $codeSnifferViolation) {
                return [
                    'file'    => $codeSnifferViolation->getFile(),
                    'reasons' => implode("\n", $codeSnifferViolation->getReasons()),
                ];
            },
            $this->violations
        );
    }
}
