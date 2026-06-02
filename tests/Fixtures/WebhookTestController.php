<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony\Tests\Fixtures;

use Gando\Partner\Symfony\Attribute\GandoWebhook;
use Gando\Partner\Symfony\EventListener\GandoWebhookListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class WebhookTestController
{
    public static bool $reached = false;

    #[GandoWebhook]
    public function protected(Request $request): Response
    {
        self::$reached = true;

        return new Response('ok', Response::HTTP_OK, [
            'X-Gando-Verified' => $request->attributes->get(GandoWebhookListener::REQUEST_ATTR_VERIFIED) ? '1' : '0',
        ]);
    }

    public function open(): Response
    {
        self::$reached = true;

        return new Response('open');
    }
}
