<?php

declare(strict_types=1);

/*
 * This file is part of the Docs Builder package.
 * (c) Ryan Weaver <ryan@symfonycasts.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SymfonyDocsBuilder\Release;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GithubApiHttpClientFactory
{
    private $githubApiToken;

    public function __construct()
    {
        if (!isset($_SERVER['GITHUB_API_TOKEN'])) {
            throw new \RuntimeException('Missing "GITHUB_API_TOKEN" environment variable');
        }

        $this->githubApiToken = $_SERVER['GITHUB_API_TOKEN'];
    }

    public function createHttpClient(): HttpClientInterface
    {
        $client = HttpClient::create(
            [
                'headers' => [
                    'Authorization' => sprintf('token %s', $this->githubApiToken),
                ],
            ]
        );

        return $client;
    }
}
