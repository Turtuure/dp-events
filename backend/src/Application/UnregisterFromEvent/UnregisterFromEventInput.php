<?php

declare(strict_types=1);

namespace DaemsModule\Events\Application\UnregisterFromEvent;

use Daems\Domain\Auth\ActingUser;

final class UnregisterFromEventInput
{
    public function __construct(
        public readonly ActingUser $acting,
        public readonly string $slug,
    ) {}
}
