<?php

namespace App\Mjml;

use Mjml\Client;
use NotFloran\MjmlBundle\Renderer\RendererInterface;

class ApiRenderer implements RendererInterface
{
    /**
     * @var Client
     */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function render(string $mjmlContent): string
    {
        return $this->client->render($mjmlContent);
    }
}