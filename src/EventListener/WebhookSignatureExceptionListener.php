<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony\EventListener;

use Gando\Partner\Exceptions\WebhookSignatureException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

final class WebhookSignatureExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        if (! $event->getThrowable() instanceof WebhookSignatureException) {
            return;
        }

        $event->setResponse(new Response('', Response::HTTP_BAD_REQUEST));
        $event->stopPropagation();
    }
}
