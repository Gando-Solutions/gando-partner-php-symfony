<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony\EventListener;

use Gando\Partner\Symfony\Attribute\GandoWebhook;
use Gando\Partner\WebhookVerifier;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

final class GandoWebhookListener
{
    public const REQUEST_ATTR_VERIFIED = '_gando_webhook.verified';

    public const REQUEST_ATTR_EVENT = '_gando_webhook.event';

    public function __construct(
        private readonly ?string $defaultSecret = null,
        private readonly int $toleranceSeconds = 300,
    ) {
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $attribute = $this->resolveAttribute($event);

        if ($attribute === null) {
            return;
        }

        $secret = $attribute->secret ?? $this->defaultSecret;

        if ($secret === null || $secret === '') {
            throw new \LogicException(
                'A controller is marked with #[GandoWebhook] but no webhook secret is configured. '
                .'Set gando_partner.webhooks.secret or pass a secret to the attribute.',
            );
        }

        $request = $event->getRequest();

        WebhookVerifier::verify(
            $request->getContent(),
            (string) $request->headers->get('X-Gando-Signature', ''),
            (string) $request->headers->get('X-Gando-Timestamp', ''),
            $secret,
            $this->toleranceSeconds,
        );

        $request->attributes->set(self::REQUEST_ATTR_VERIFIED, true);
        $request->attributes->set(self::REQUEST_ATTR_EVENT, $request->headers->get('X-Gando-Event', ''));
    }

    private function resolveAttribute(ControllerEvent $event): ?GandoWebhook
    {
        $controller = $event->getController();

        if (\is_array($controller)) {
            [$object, $method] = $controller;

            if (! \is_object($object)) {
                return null;
            }

            return $this->readAttribute($object, $method)
                ?? $this->readAttribute($object, null);
        }

        if (\is_object($controller)) {
            return $this->readAttribute($controller, '__invoke')
                ?? $this->readAttribute($controller, null);
        }

        return null;
    }

    private function readAttribute(object $object, ?string $method): ?GandoWebhook
    {
        $reflection = $method !== null
            ? new \ReflectionMethod($object, $method)
            : new \ReflectionClass($object);

        $attributes = $reflection->getAttributes(GandoWebhook::class);

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }
}
