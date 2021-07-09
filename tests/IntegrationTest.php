<?php

/*
 * This file is part of the Docs Builder package.
 * (c) Ryan Weaver <ryan@symfonycasts.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyDocsBuilder\Tests;

use Doctrine\RST\Configuration;
use Doctrine\RST\Parser;
use Gajus\Dindent\Indenter;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Finder\Finder;
use SymfonyDocsBuilder\DocBuilder;
use SymfonyDocsBuilder\KernelFactory;

class IntegrationTest extends AbstractIntegrationTest
{
    /**
     * @dataProvider integrationProvider
     */
    public function testIntegration(string $folder)
    {
        $buildConfig = $this->createBuildConfig(sprintf('%s/fixtures/source/%s', __DIR__, $folder));
        $builder = new DocBuilder();
        $builder->build($buildConfig);

        $finder = new Finder();
        $finder->in(sprintf('%s/fixtures/expected/%s', __DIR__, $folder))
            ->files()
            ->depth('>=0');

        $indenter = $this->createIndenter();
        foreach ($finder as $expectedFile) {
            $relativePath = $expectedFile->getRelativePathname();
            $actualFilename = $buildConfig->getOutputDir().'/'.$relativePath;
            $this->assertFileExists($actualFilename);

            $this->assertSame(
                // removes odd trailing space the indenter is adding
                str_replace(" \n", "\n", $indenter->indent($expectedFile->getContents())),
                str_replace(" \n", "\n", $indenter->indent(file_get_contents($actualFilename))),
                sprintf('File %s is not equal', $relativePath)
            );
        }

        foreach ($finder as $htmlFile) {
            $relativePath = $htmlFile->getRelativePathname();
            $actualFilename = $buildConfig->getOutputDir().'/'.str_replace('.html', '.fjson', $relativePath);
            $this->assertFileExists($actualFilename);

            $jsonData = json_decode(file_get_contents($actualFilename), true);
            $crawler = new Crawler($htmlFile->getContents());

            $this->assertSame(
                str_replace(" \n", "\n", $indenter->indent($crawler->filter('body')->html())),
                str_replace(" \n", "\n", $indenter->indent($jsonData['body']))
            );
            $this->assertSame($crawler->filter('h1')->first()->text(), $jsonData['title']);
        }
    }

    public function integrationProvider()
    {
        yield 'main' => [
            'folder' => 'main',
        ];

        yield 'toctree' => [
            'folder' => 'toctree',
        ];

        yield 'ref-reference' => [
            'folder' => 'ref-reference',
        ];

        yield 'doc-reference' => [
            'folder' => 'doc-reference',
        ];
    }

    /**
     * @dataProvider parserUnitBlockProvider
     */
    public function testParseUnitBlock(string $blockName)
    {
        $configuration = new Configuration();
        $configuration->setCustomTemplateDirs([__DIR__.'/Templates']);

        $parser = new Parser(
            KernelFactory::createKernel($this->createBuildConfig(sprintf('%s/fixtures/source/blocks', __DIR__)))
        );

        $sourceFile = sprintf('%s/fixtures/source/blocks/%s.rst', __DIR__, $blockName);

        $actualHtml = $parser->parseFile($sourceFile)->renderDocument();
        $expectedHtml = file_get_contents(sprintf('%s/fixtures/expected/blocks/%s.html', __DIR__, $blockName));

        $actualCrawler = new Crawler($actualHtml);
        $expectedCrawler = new Crawler($expectedHtml);
        $indenter = $this->createIndenter();

        $expected = trim($expectedCrawler->filter('body')->html());
        // you can add notes to a test file via <!-- REMOVE the notes here -->
        // we remove them here for comparing
        $expected = preg_replace('/<\!\-\- REMOVE(.)+\-\->/', '', $expected);

        $this->assertSame(
            $indenter->indent($expected),
            $indenter->indent(trim($actualCrawler->filter('body')->html()))
        );
    }

    public function parserUnitBlockProvider()
    {
        yield 'tables' => [
            'blockName' => 'nodes/tables',
        ];

        yield 'literal' => [
            'blockName' => 'nodes/literal',
        ];

        yield 'list' => [
            'blockName' => 'nodes/list',
        ];

        yield 'figure' => [
            'blockName' => 'nodes/figure',
        ];

        yield 'caution' => [
            'blockName' => 'directives/caution',
        ];

        yield 'note' => [
            'blockName' => 'directives/note',
        ];

        yield 'admonition' => [
            'blockName' => 'directives/admonition',
        ];

        yield 'note-code-block-nested' => [
            'blockName' => 'directives/note-code-block-nested',
        ];

        yield 'seealso' => [
            'blockName' => 'directives/seealso',
        ];

        yield 'tip' => [
            'blockName' => 'directives/tip',
        ];

        yield 'topic' => [
            'blockName' => 'directives/topic',
        ];

        yield 'best-practice' => [
            'blockName' => 'directives/best-practice',
        ];

        yield 'versionadded' => [
            'blockName' => 'directives/versionadded',
        ];

        yield 'class' => [
            'blockName' => 'directives/class',
        ];

        yield 'configuration-block' => [
            'blockName' => 'directives/configuration-block',
        ];

        yield 'sidebar' => [
            'blockName' => 'directives/sidebar',
        ];

        yield 'sidebar-code-block-nested' => [
            'blockName' => 'directives/sidebar-code-block-nested',
        ];

        yield 'class-reference' => [
            'blockName' => 'references/class',
        ];

        yield 'namespace-reference' => [
            'blockName' => 'references/namespace',
        ];

        yield 'method-reference' => [
            'blockName' => 'references/method',
        ];

        yield 'php-class-reference' => [
            'blockName' => 'references/php-class',
        ];

        yield 'php-function-reference' => [
            'blockName' => 'references/php-function',
        ];

        yield 'php-method-reference' => [
            'blockName' => 'references/php-method',
        ];

        yield 'reference-and-code' => [
            'blockName' => 'references/reference-and-code',
        ];

        yield 'code-block-php' => [
            'blockName' => 'code-blocks/php',
        ];

        yield 'code-block-html' => [
            'blockName' => 'code-blocks/html',
        ];

        yield 'code-block-twig' => [
            'blockName' => 'code-blocks/twig',
        ];

        yield 'code-block-html-twig' => [
            'blockName' => 'code-blocks/html-twig',
        ];

        yield 'code-block-xml' => [
            'blockName' => 'code-blocks/xml',
        ];

        yield 'code-block-yaml' => [
            'blockName' => 'code-blocks/yaml',
        ];

        yield 'code-block-ini' => [
            'blockName' => 'code-blocks/ini',
        ];

        yield 'code-block-bash' => [
            'blockName' => 'code-blocks/bash',
        ];

        yield 'code-block-html-php' => [
            'blockName' => 'code-blocks/html-php',
        ];

        yield 'code-block-php-annotations' => [
            'blockName' => 'code-blocks/php-annotations',
        ];

        yield 'code-block-text' => [
            'blockName' => 'code-blocks/text',
        ];

        yield 'code-block-terminal' => [
            'blockName' => 'code-blocks/terminal',
        ];
    }

    public function testParseString()
    {
        $rstString = <<<RST
Lorem ipsum dolor sit amet
==========================

Consectetur adipisicing elit, sed do eiusmod
tempor **incididunt ut** labore et dolore magna aliqua.

* Ut enim ad minim veniam
* Quis nostrud exercitation
* Ullamco laboris nisi ut

`Aliquip ex ea commodo <https://symfony.com>`_ consequat.
Duis aute irure dolor in reprehenderit in voluptate `velit esse`_.

Cillum dolore eu fugiat nulla pariatur
--------------------------------------

Excepteur sint occaecat cupidatat non proident, sunt in
culpa qui *officia deserunt* mollit anim id est laborum.

.. _`velit esse`: https://github.com
RST;

        $htmlString = <<<HTML
<div class="section">
<h1 id="lorem-ipsum-dolor-sit-amet"><a class="headerlink" href="#lorem-ipsum-dolor-sit-amet" title="Permalink to this headline">Lorem ipsum dolor sit amet</a></h1>
<p>Consectetur adipisicing elit, sed do eiusmod
tempor <strong>incididunt ut</strong> labore et dolore magna aliqua.</p>
<ul>
    <li>Ut enim ad minim veniam</li>
<li>Quis nostrud exercitation</li>
<li>Ullamco laboris nisi ut</li>
</ul>
<p><a href="https://symfony.com" class="reference external">Aliquip ex ea commodo</a> consequat.
Duis aute irure dolor in reprehenderit in voluptate <a href="https://github.com" class="reference external" rel="external noopener noreferrer" target="_blank">velit esse</a>.</p>
<div class="section">
<h2 id="cillum-dolore-eu-fugiat-nulla-pariatur"><a class="headerlink" href="#cillum-dolore-eu-fugiat-nulla-pariatur" title="Permalink to this headline">Cillum dolore eu fugiat nulla pariatur</a></h2>
<p>Excepteur sint occaecat cupidatat non proident, sunt in
culpa qui <em>officia deserunt</em> mollit anim id est laborum.</p>
</div>
</div>
HTML;

        $this->assertSame($htmlString, (new DocBuilder())->buildString($rstString)->getStringResult());
    }

    private function createIndenter(): Indenter
    {
        $indenter = new Indenter();
        // indent spans - easier to debug failures
        $indenter->setElementType('span', Indenter::ELEMENT_TYPE_BLOCK);

        return $indenter;
    }
}
