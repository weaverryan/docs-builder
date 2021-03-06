<?php

declare(strict_types=1);

/*
 * This file is part of the Docs Builder package.
 * (c) Ryan Weaver <ryan@symfonycasts.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyDocsBuilder\Release;

use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use SymfonyDocsBuilder\Phar\Compiler;
use SymfonyDocsBuilder\Release\Exception\DeleteReleaseFailed;
use SymfonyDocsBuilder\Release\Exception\ReleaseFailed;

class Releaser
{
    private const GITHUB_USER = 'weaverryan';
    private const GITHUB_REPO = 'docs-builder';

    /** @var HttpClientInterface */
    private $client;
    private $compiler;

    public function __construct(HttpClientInterface $client, Compiler $compiler)
    {
        $this->client = $client;
        $this->compiler = $compiler;
    }

    public function createRelease(string $tag, string $name = 'Symfony docs builder %s', string $description = 'Symfony docs builder %s'): void
    {
        if (!preg_match('/^v\d+\.\d+\.\d+$/', $tag)) {
            throw new \RuntimeException(sprintf('"%s" is not a valid tag.', $tag));
        }

        $this->compiler->compile();

        $this->addAssetToRelease($releaseId = $this->createDraftRelease($tag, $name, $description));

        $this->publishRelease($releaseId);
    }

    private function createDraftRelease(string $tag, string $name, string $description): int
    {
        try {
            $response = $this->client->request(
                'POST',
                sprintf('https://api.github.com/repos/%s/%s/releases', self::GITHUB_USER, self::GITHUB_REPO),
                [
                    'json' => [
                        'tag_name' => $tag,
                        'target_commitish' => 'master',
                        'name' => sprintf($name, $tag),
                        'description' => sprintf($description, $tag),
                        'draft' => true,
                        'prerelease' => false,
                    ],
                ]
            );

            return (int) $response->toArray()['id'];
        } catch (HttpExceptionInterface $exception) {
            throw ReleaseFailed::whileCreatingDraft($exception);
        }
    }

    private function addAssetToRelease(int $releaseId): void
    {
        try {
            $this->client->request(
                'POST',
                sprintf(
                    'https://uploads.github.com/repos/%s/%s/releases/%s/assets?name=docs.phar',
                    self::GITHUB_USER,
                    self::GITHUB_REPO,
                    $releaseId
                ),
                [
                    'headers' => ['Content-Type' => 'application/octet-stream'],
                    'body' => file_get_contents(__DIR__.'/../../docs.phar'),
                ]
            );
        } catch (HttpExceptionInterface $exception) {
            $this->deleteRelease($releaseId, ReleaseFailed::whileAttachingAssetToRelease($exception));
        }
    }

    private function publishRelease(int $releaseId): void
    {
        try {
            $this->client->request(
                'PATCH',
                sprintf('https://api.github.com/repos/%s/%s/releases/%s', self::GITHUB_USER, self::GITHUB_REPO, $releaseId),
                [
                    'json' => [
                        'draft' => false,
                    ],
                ]
            );
        } catch (HttpExceptionInterface $exception) {
            $this->deleteRelease($releaseId, ReleaseFailed::whilePublishingRelease($exception));
        }
    }

    private function deleteRelease(int $releaseId, ReleaseFailed $previous): void
    {
        try {
            $this->client->request(
                'DELETE',
                sprintf('https://api.github.com/repos/%s/%s/releases/%s', self::GITHUB_USER, self::GITHUB_REPO, $releaseId)
            );
        } catch (HttpExceptionInterface $exception) {
            throw new DeleteReleaseFailed($previous, $exception);
        }

        throw $previous;
    }
}
