<?php

namespace App\State\Site;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Partner;
use App\Repository\SiteContactRepository;
use Symfony\Component\HttpFoundation\RequestStack;

final class SiteContactCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly SiteContactRepository $siteContactRepository,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $site = $this->requestStack->getCurrentRequest()?->query->getString('site', '');

        if ($site !== '' && \in_array($site, [Partner::SITE_IDIOMA, Partner::SITE_STRATON], true)) {
            return $this->siteContactRepository->findActiveForSite($site);
        }

        return $this->siteContactRepository->findBy(['isActive' => true]);
    }
}
