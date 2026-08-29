<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use App\Repository\FaqRepository;
use App\State\Faq\FaqCollectionProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: FaqRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['faq:get']],
            provider: FaqCollectionProvider::class,
            paginationEnabled: false,
            parameters: [
                'site' => new QueryParameter(description: 'Filter by site (idioma or straton)'),
            ],
        ),
    ],
    normalizationContext: ['groups' => ['faq:get']],
)]
class Faq
{
    public const SITE_IDIOMA = 'idioma';
    public const SITE_STRATON = 'straton';
    public const SITE_BOTH = 'both';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['faq:get'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['faq:get'])]
    private ?string $question = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['faq:get'])]
    private ?string $answer = null;

    #[ORM\Column]
    #[Groups(['faq:get'])]
    private int $position = 0;

    #[ORM\Column]
    #[Groups(['faq:get'])]
    private bool $isActive = true;

    /** idioma | straton | both */
    #[ORM\Column(length: 20, options: ['default' => 'both'])]
    #[Groups(['faq:get'])]
    private string $site = self::SITE_BOTH;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuestion(): ?string
    {
        return $this->question;
    }

    public function setQuestion(string $question): static
    {
        $this->question = $question;

        return $this;
    }

    public function getAnswer(): ?string
    {
        return $this->answer;
    }

    public function setAnswer(string $answer): static
    {
        $this->answer = $answer;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getSite(): string
    {
        return $this->site;
    }

    public function setSite(string $site): static
    {
        $this->site = $site;

        return $this;
    }

    public function __toString(): string
    {
        return $this->question ?? 'FAQ';
    }
}
