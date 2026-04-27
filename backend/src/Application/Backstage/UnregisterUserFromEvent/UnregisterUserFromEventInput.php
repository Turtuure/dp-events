<?php

declare(strict_types=1);

namespace DaemsModule\Events\Application\Backstage\UnregisterUserFromEvent;

use Daems\Domain\Auth\ActingUser;

final class UnregisterUserFromEventInput
{
    public function __construct(
        public readonly ActingUser $acting,
        public readonly string $eventId,
        public readonly string $userId,
    ) {}
}
