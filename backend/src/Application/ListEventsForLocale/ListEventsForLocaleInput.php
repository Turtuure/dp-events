<?php

declare(strict_types=1);

namespace DaemsModule\Events\Application\ListEventsForLocale;

use Daems\Domain\Locale\SupportedLocale;
use Daems\Domain\Tenant\TenantId;

final class ListEventsForLocaleInput
{
    public function __construct(
        public readonly TenantId $tenantId,
        public readonly SupportedLocale $locale,
        public readonly ?string $type = null,
    ) {
    }
}
