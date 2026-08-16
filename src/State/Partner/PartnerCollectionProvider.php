<?php

namespace App\State\Partner;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Partner;
use App\Repository\PartnerRepository;
use Symfony\Component\HttpFoundation\RequestStack;

final class PartnerCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly PartnerRepository $partnerRepository,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $site = $this->requestStack->getCurrentRequest()?->query->getString('site', '');

        if ($site !== '' && \in_array($site, [Partner::SITE_IDIOMA, Partner::SITE_STRATON], true)) {
            return $this->partnerRepository->findActiveForSite($site);
        }

        return $this->partnerRepository->findBy(
            ['isActive' => true],
            ['position' => 'ASC', 'name' => 'ASC']
        );
    }
}
