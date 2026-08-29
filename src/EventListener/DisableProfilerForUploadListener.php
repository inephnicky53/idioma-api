<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Profiler\Profiler;

/**
 * Large multipart uploads can exhaust memory when the dev profiler stores the full request body.
 */
final class DisableProfilerForUploadListener implements EventSubscriberInterface
{
    private const UPLOAD_PATHS = [
        '/api/teachers/become',
        '/api/teacher/media',
    ];

    public function __construct(private readonly ?Profiler $profiler = null)
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$this->profiler || !$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        foreach (self::UPLOAD_PATHS as $uploadPath) {
            if (str_starts_with($path, $uploadPath)) {
                $this->profiler->disable();

                return;
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 4096]];
    }
}
