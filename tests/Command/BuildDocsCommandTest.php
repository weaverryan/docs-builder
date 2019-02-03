<?php declare(strict_types=1);

namespace App\Tests\Command;

use Gajus\Dindent\Indenter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use SymfonyDocsBuilder\BuildContext;
use SymfonyDocsBuilder\Command\BuildDocsCommand;

class BuildDocsCommandTest extends TestCase
{
    public function testBuildDocs()
    {
        $buildContext = $this->createParameterBag();
        $outputDir    = sprintf('%s/tests/_output', $buildContext->getBasePath());

        $output = $this->executeCommand(
            $buildContext,
            [
                'source-dir' => sprintf('%s/tests/fixtures/source/main', $buildContext->getBasePath()),
                'output-dir' => $outputDir,
            ]
        );

        // no code sniffer violation should be found
        $this->assertNotContains('[ERROR]', $output, 'Code sniffer violation found.');

        $this->assertEquals(1, substr_count($output, '[WARNING]'));
        $this->assertContains('[OK] Parse process complete', $output, 'Parse process was not successful');

        $filesystem = new Filesystem();
        $this->assertTrue($filesystem->exists(sprintf('%s/_images/symfony-logo.png', $outputDir)));
    }

    public function testBuildDocsWithCodeSnifferViolation()
    {
        $buildContext = $this->createParameterBag();
        $outputDir    = sprintf('%s/tests/_output', $buildContext->getBasePath());

        $output = $this->executeCommand(
            $buildContext,
            [
                'source-dir' => sprintf('%s/tests/fixtures/source/code-sniffer-violation', $buildContext->getBasePath()),
                'output-dir' => $outputDir,
            ]
        );

        // code sniffer violation found
        $this->assertContains('[ERROR]', $output, 'No code sniffer violation found.');
    }

    public function testBuildDocsForPdf()
    {
        $buildContext = $this->createParameterBag();
        $outputDir    = sprintf('%s/tests/_output', $buildContext->getBasePath());

        $output = $this->executeCommand(
            $buildContext,
            [
                'source-dir'   => sprintf('%s/tests/fixtures/source/build-pdf', $buildContext->getBasePath()),
                'output-dir'   => $outputDir,
                '--parse-sub-path' => 'book',
            ]
        );

        $this->assertNotContains('[ERROR]', $output);
        $this->assertNotContains('[WARNING]', $output);

        $filesystem = new Filesystem();
        $this->assertTrue($filesystem->exists(sprintf('%s/_images/symfony-logo.png', $outputDir)));
        $this->assertTrue($filesystem->exists(sprintf('%s/book.html', $outputDir)));

        $finder = new Finder();
        $finder->in($outputDir)
            ->files()
            ->name('*.html');
        $this->assertCount(1, $finder);

        $indenter = new Indenter();
        $this->assertSame(
            $indenter->indent(file_get_contents(sprintf('%s/../fixtures/expected/build-pdf/book.html', __DIR__))),
            $indenter->indent(file_get_contents(sprintf('%s/book.html', $outputDir)))
        );
    }

    private function executeCommand(BuildContext $buildContext, array $input): string
    {
        $fs = new Filesystem();
        if ($fs->exists($input['output-dir'])) {
            $fs->remove($input['output-dir']);
        }

        $command       = new BuildDocsCommand($buildContext);
        $commandTester = new CommandTester($command);
        $commandTester->execute($input);

        return $commandTester->getDisplay();
    }

    private function createParameterBag(): BuildContext
    {
        $buildContext = new BuildContext(
            realpath(__DIR__.'/../..'),
            '4.0',
            'https://api.symfony.com/4.0',
            'https://secure.php.net/manual/en',
            'https://symfony.com/doc/4.0'
        );

        return $buildContext;
    }
}