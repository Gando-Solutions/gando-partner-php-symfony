<?php

declare(strict_types=1);

namespace Gando\Partner\Symfony\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class GandoWebhook
{
    public function __construct(
        public readonly ?string $secret = null,
    ) {
    }
}
