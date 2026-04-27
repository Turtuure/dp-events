<?php

declare(strict_types=1);

namespace DaemsModule\Events\Application\Backstage\GetEventWithAllTranslations;

final class GetEventWithAllTranslationsOutput
{
    /**
     * @param array<string, mixed> $event
     */
    public function __construct(public readonly array $event)
    {
    }
}
