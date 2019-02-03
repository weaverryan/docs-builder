<?php declare(strict_types=1);

namespace SymfonyDocsBuilder\Command;

use Doctrine\RST\Event\PostBuildRenderEvent;
use Doctrine\RST\Event\PostNodeRenderEvent;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use SymfonyDocsBuilder\BuildContext;
use SymfonyDocsBuilder\CI\CodeSniffer\CodeSnifferViolationsList;
use SymfonyDocsBuilder\CI\MissingFilesChecker;
use SymfonyDocsBuilder\Generator\HtmlForPdfGenerator;
use SymfonyDocsBuilder\Generator\JsonGenerator;
use SymfonyDocsBuilder\Listener\CodeSnifferListener;
use SymfonyDocsBuilder\Listener\CopyImagesDirectoryListener;

class BuildDocsCommand extends Command
{
    use CommandInitializerTrait;

    protected static $defaultName = 'build:docs';

    private $missingFilesChecker;
    private $codeSnifferViolationsList;

    public function __construct(BuildContext $buildContext)
    {
        parent::__construct(self::$defaultName);

        $this->filesystem   = new Filesystem();
        $this->finder       = new Finder();
        $this->buildContext = $buildContext;

        $this->missingFilesChecker       = new MissingFilesChecker($buildContext);
        $this->codeSnifferViolationsList = new CodeSnifferViolationsList();
    }

    protected function configure()
    {
        parent::configure();

        $this
            ->addArgument('source-dir', InputArgument::OPTIONAL, 'RST files Source directory', getcwd())
            ->addArgument('output-dir', InputArgument::OPTIONAL, 'HTML files output directory')
            ->addOption(
                'parse-sub-path',
                null,
                InputOption::VALUE_OPTIONAL,
                'Parse only given sub directory and combine it into a single file (directory relative from source-dir)',
                ''
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $sourceDir = $this->initializeSourceDir($input, $this->filesystem);
        $outputDir = $input->getArgument('output-dir') ?? $sourceDir.'/html';

        $this->doInitialize($input, $output, $sourceDir, $outputDir);

        $this->eventManager->addEventListener(
            PostBuildRenderEvent::POST_BUILD_RENDER,
            new CopyImagesDirectoryListener($this->buildContext)
        );

        $this->eventManager->addEventListener(
            PostNodeRenderEvent::POST_NODE_RENDER,
            new CodeSnifferListener($this->codeSnifferViolationsList)
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->startBuild();

        if (\count($this->codeSnifferViolationsList)) {
            $this->io->newLine(2);
            $this->io->table(['file', 'reason(s)'], $this->codeSnifferViolationsList->getViolationsAsArray());
            $this->filesystem->remove($this->buildContext->getHtmlOutputDir());

            $this->io->error('Code sniffer violations detected! Please fix them.');

            return;
        }

        $this->io->newLine(2);
        $this->io->success('HTML rendering complete!');

        $this->missingFilesChecker->checkMissingFiles($this->io);

        if (!$this->buildContext->getParseSubPath()) {
            $this->generateJson();
        } else {
            $this->renderDocForPDF();
        }

        $this->io->newLine(2);
        $this->io->success('Parse process complete');
    }

    private function generateJson()
    {
        $this->io->note('Start exporting doc into json files');
        $this->progressBar = new ProgressBar($this->output, $this->finder->count());

        $jsonGenerator = new JsonGenerator();
        $jsonGenerator->generateJson($this->builder->getDocuments()->getAll(), $this->buildContext, $this->progressBar);
    }

    private function renderDocForPDF()
    {
        $htmlForPdfGenerator = new HtmlForPdfGenerator();
        $htmlForPdfGenerator->generateHtmlForPdf($this->builder->getDocuments()->getAll(), $this->buildContext);
    }

    public function preBuildRender()
    {
        $this->doPreBuildRender();

        $this->io->note('Start rendering in HTML...');
    }
}
