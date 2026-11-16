<?php

declare(strict_types=1);

namespace Gabrieldav1108\ServerAnalytics;

final readonly class Actor
{
    public function __construct(
        public ?string $tenant,
        public ?string $userId,
    ) {}

    public static function anonymous(): self
    {
        return new self(tenant: null, userId: null);
    }
}
