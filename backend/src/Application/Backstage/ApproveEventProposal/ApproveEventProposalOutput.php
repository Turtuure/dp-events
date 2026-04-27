<?php

declare(strict_types=1);

namespace DaemsModule\Events\Application\Backstage\ApproveEventProposal;

final class ApproveEventProposalOutput
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $slug,
    ) {
    }
}
