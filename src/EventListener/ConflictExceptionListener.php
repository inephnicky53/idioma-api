<?php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ConflictExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Vérifier si c'est une requête API et une ConflictHttpException
        if (!str_contains($request->getPathInfo(), '/api/') || !$exception instanceof ConflictHttpException) {
            return;
        }

        $message = $exception->getMessage();
        $field = null;

        // Déterminer le champ en erreur basé sur le message
        if (str_contains($message, 'email') || str_contains($message, 'Email')) {
            $field = 'email';
        } elseif (str_contains($message, 'téléphone') || str_contains($message, 'phone') || str_contains($message, 'Phone')) {
            $field = 'phone';
        }

        $response = [
            'status' => 409,
            'type' => 'conflict',
            'message' => $message,
            'errors' => [],
        ];

        // Si on a identifié un champ, ajouter l'erreur au champ
        if ($field) {
            $response['errors'][$field] = $message;
        }

        $event->setResponse(new JsonResponse($response, 409));
    }
}

