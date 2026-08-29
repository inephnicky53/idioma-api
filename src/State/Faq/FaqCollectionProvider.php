<?php

namespace App\State\Faq;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Partner;
use App\Repository\FaqRepository;
use Symfony\Component\HttpFoundation\RequestStack;

final class FaqCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly FaqRepository $faqRepository,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $site = $this->requestStack->getCurrentRequest()?->query->getString('site', '');

        if ($site !== '' && \in_array($site, [Partner::SITE_IDIOMA, Partner::SITE_STRATON], true)) {
            return $this->faqRepository->findActiveForSite($site);
        }

        return $this->faqRepository->findBy(
            ['isActive' => true],
            ['position' => 'ASC', 'id' => 'ASC']
        );
    }
}
