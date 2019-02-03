<?php declare(strict_types=1);

namespace SymfonyDocsBuilder\Listener;

use Doctrine\RST\Event\PostNodeRenderEvent;
use Doctrine\RST\Nodes\CodeNode;
use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Console\Application;
use PhpCsFixer\FixerFactory;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use SymfonyDocsBuilder\CI\CodeSniffer\CodeSnifferViolationsList;
use SymfonyDocsBuilder\Renderers\CodeNodeRenderer;

class CodeSnifferListener
{
    private $application;
    private $fixerFactory;
    private $fixers;
    private $codeSnifferViolationsList;

    public function __construct(CodeSnifferViolationsList $codeSnifferViolationsList)
    {
        $this->application = new Application();
        $this->application->setAutoExit(false);

        $this->fixerFactory = new FixerFactory();
        $this->fixerFactory->registerBuiltInFixers();

        $this->codeSnifferViolationsList = $codeSnifferViolationsList;
    }

    public function postNodeRender(PostNodeRenderEvent $postNodeRenderEvent)
    {
        $node = $postNodeRenderEvent->getRenderedNode()->getNode();
        if (!$node instanceof CodeNode) {
            return;
        }

        $language = $node->getLanguage();

        if ('php' !== (CodeNodeRenderer::LANGUAGES_MAPPING[$language] ?? $language)) {
            return;
        }

        $code = $node->getValue();

        if (false === strpos($code, '<?php')) {
            $code = "<?php\n\n".$code;
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'symfony-docs-builder').'.php';
        file_put_contents($tempFile, $code);

        $input = new ArrayInput(
            [
                'command'       => 'fix',
                'path'          => [$tempFile],
                '--format'      => 'json',
                '--dry-run'     => true,
                '-vvv'          => true,
                '--using-cache' => 'no',
                '--config'      => '/home/niko/works/docs-builder/.php_cs',
            ]
        );

        $output = new BufferedOutput();

        $this->application->run($input, $output);
        $content = json_decode($output->fetch(), true);

        if (count($content['files']) > 0) {
            $this->codeSnifferViolationsList->add(
                $postNodeRenderEvent->getRenderedNode()->getNode()->getEnvironment()->getCurrentFileName(),
                $code,
                array_map([$this, 'getFixerSummary'], $content['files'][0]['appliedFixers'])
            );
        }
    }

    /**
     * @return AbstractFixer[]
     */
    private function getFixers(): array
    {
        if (null !== $this->fixers) {
            return $this->fixers;
        }

        $fixers = [];
        foreach ($this->fixerFactory->getFixers() as $fixer) {
            $fixers[$fixer->getName()] = $fixer;
        }

        $this->fixers = $fixers;
        ksort($this->fixers);

        return $this->fixers;
    }

    private function getFixerSummary($name)
    {
        $fixers = $this->getFixers();

        $fixer = $fixers[$name];

        return sprintf("    - %s", $fixer->getDefinition()->getSummary());
    }
}
