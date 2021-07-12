<?php

declare(strict_types=1);

/*
 * This file is part of the Docs Builder package.
 * (c) Ryan Weaver <ryan@symfonycasts.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyDocsBuilder\Command;

use Doctrine\Common\EventManager;
use Doctrine\RST\Builder;
use Doctrine\RST\Configuration;
use Doctrine\RST\Meta\Metas;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use SymfonyDocsBuilder\BuildConfig;
use SymfonyDocsBuilder\CI\MissingFilesChecker;
use SymfonyDocsBuilder\ConfigFileParser;
use SymfonyDocsBuilder\Generator\HtmlForPdfGenerator;
use SymfonyDocsBuilder\Generator\JsonGenerator;
use SymfonyDocsBuilder\KernelFactory;
use SymfonyDocsBuilder\Listener\BuildProgressListener;

class BuildDocsCommand extends Command
{
    protected static $defaultName = 'build:docs';

    private $buildConfig;
    private $missingFilesChecker;
    /** @var SymfonyStyle */
    private $io;

    public function __construct(BuildConfig $buildConfig)
    {
        parent::__construct(self::$defaultName);

        $this->buildConfig = $buildConfig;
        $this->missingFilesChecker = new MissingFilesChecker($buildConfig);
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
            )
            ->addOption(
                'output-json',
                null,
                InputOption::VALUE_NONE,
                'If provided, .fjson metadata files will be written'
            )
            ->addOption(
                'disable-cache',
                null,
                InputOption::VALUE_NONE,
                'If provided, caching meta will be disabled'
            )
            ->addOption(
                'save-errors',
                null,
                InputOption::VALUE_REQUIRED,
                'Path where any errors should be saved'
            )
            ->addOption(
                'disable-parse-errors-on-stdout',
                null,
                InputOption::VALUE_NONE,
                'Don\'t print errors to standard output'
            )
            ->addOption(
                'no-theme',
                null,
                InputOption::VALUE_NONE,
                'Use the default theme instead of the styled one'
            )
            ->addOption(
                'fail-on-errors',
                null,
                InputOption::VALUE_NONE,
                'Return a non-zero code if there are errors/warnings'
            )
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $sourceDir = $input->getArgument('source-dir');
        if (!file_exists($sourceDir)) {
            throw new \InvalidArgumentException(sprintf('RST source directory "%s" does not exist', $sourceDir));
        }
        $this->buildConfig->setContentDir($sourceDir);

        $filesystem = new Filesystem();
        $htmlOutputDir = $input->getArgument('output-dir') ?? rtrim(getcwd(), '/').'/html';
        if ($input->getOption('disable-cache') && $filesystem->exists($htmlOutputDir)) {
            $filesystem->remove($htmlOutputDir);
        }
        $filesystem->mkdir($htmlOutputDir);
        $this->buildConfig->setOutputDir($htmlOutputDir);

        $parseSubPath = $input->getOption('parse-sub-path');
        if ($parseSubPath && $input->getOption('output-json')) {
            throw new \InvalidArgumentException('Cannot pass both --parse-sub-path and --output-json options.');
        }
        if (!file_exists($sourceDir.'/'.$parseSubPath)) {
            throw new \InvalidArgumentException(sprintf('Given "parse-sub-path" directory "%s" does not exist', $parseSubPath));
        }
        $this->buildConfig->setSubdirectoryToBuild($parseSubPath);

        if ($input->getOption('disable-cache')) {
            $this->buildConfig->disableBuildCache();
        }

        $this->buildConfig->setTheme($input->getOption('no-theme') ? Configuration::THEME_DEFAULT : 'rtd');

        $configFileParser = new ConfigFileParser($this->buildConfig, $output);
        $configFileParser->processConfigFile($sourceDir);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $builder = new Builder(
            KernelFactory::createKernel($this->buildConfig, $this->urlChecker ?? null)
        );

        $configuration = $builder->getConfiguration();
        $configuration->silentOnError($input->getOption('disable-parse-errors-on-stdout'));
        $this->addProgressListener($configuration->getEventManager());

        $builder->build(
            $this->buildConfig->getContentDir(),
            $this->buildConfig->getOutputDir()
        );

        $buildErrors = $builder->getErrorManager()->getErrors();

        $missingFiles = $this->missingFilesChecker->getMissingFiles();
        foreach ($missingFiles as $missingFile) {
            $message = sprintf('Missing file "%s"', $missingFile);
            $buildErrors[] = $message;
            $this->io->warning($message);
        }

        if ($logPath = $input->getOption('save-errors')) {
            if (\count($buildErrors) > 0) {
                array_unshift($buildErrors, sprintf('Build errors from "%s"', date('Y-m-d h:i:s')));
            }

            $filesystem = new Filesystem();
            $filesystem->dumpFile($logPath, implode("\n", $buildErrors));
        }

        $metas = $builder->getMetas();
        if ($this->buildConfig->getSubdirectoryToBuild()) {
            $this->renderDocForPDF($metas);
        } elseif ($input->getOption('output-json')) {
            $this->generateJson($metas);
        }

        $this->io->newLine(2);

        if (\count($buildErrors) > 0) {
            $this->io->success('Build completed with warnings');

            if ($input->getOption('fail-on-errors')) {
                return 1;
            }
        } else {
            $this->io->success('Build completed successfully!');
        }

        return Command::SUCCESS;
    }

    private function generateJson(Metas $metas)
    {
        $this->io->note('Start exporting doc into json files');

        $jsonGenerator = new JsonGenerator($metas, $this->buildConfig);
        $jsonGenerator->setOutput($this->io);
        $jsonGenerator->generateJson();
    }

    private function renderDocForPDF(Metas $metas)
    {
        $htmlForPdfGenerator = new HtmlForPdfGenerator($metas, $this->buildConfig);
        $htmlForPdfGenerator->generateHtmlForPdf();
    }

    private function addProgressListener(EventManager $eventManager)
    {
        $progressListener = new BuildProgressListener($this->io);
        $progressListener->attachListeners($eventManager);
    }
}
