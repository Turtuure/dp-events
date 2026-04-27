<?php

declare(strict_types=1);

namespace DaemsModule\Events\Application\GetEvent;

use Daems\Domain\Tenant\TenantId;

final class GetEventInput
{
    public function __construct(
        public readonly TenantId $tenantId,
        public readonly string $slug,
        public readonly ?string $userId = null,
    ) {}
}
