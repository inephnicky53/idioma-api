<?php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\RouterInterface;

class AccessDeniedListener
{
    public function __construct(private RouterInterface $router) {}

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Vérifier si c'est une exception d'accès refusé et si c'est une route admin
        if (!$exception instanceof AccessDeniedHttpException) {
            return;
        }

        $pathInfo = $request->getPathInfo();
        
        // Si c'est une route admin, rediriger vers la page d'accueil
        if (str_starts_with($pathInfo, '/admin')) {
            // Rediriger vers la page d'accueil
            $response = new RedirectResponse($this->router->generate('app_home'));
            $event->setResponse($response);
        }
    }
}

