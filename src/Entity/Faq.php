<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\FaqRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: FaqRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection()
    ],
    normalizationContext: ['groups' => ['faq:get']]
)]
class Faq
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['faq:get'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['faq:get'])]
    private ?string $question = null;

    #[ORM\Column(length: 255)]
    #[Groups(['faq:get'])]
    private ?string $answer = null;

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
}
