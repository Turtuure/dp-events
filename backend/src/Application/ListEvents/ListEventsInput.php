<?php

declare(strict_types=1);

namespace DaemsModule\Events\Application\ListEvents;

use Daems\Domain\Tenant\TenantId;

final class ListEventsInput
{
    public function __construct(
        public readonly TenantId $tenantId,
        public readonly ?string $type = null,
    ) {}
}
