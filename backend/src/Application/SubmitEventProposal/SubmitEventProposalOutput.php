<?php

declare(strict_types=1);

namespace DaemsModule\Events\Application\SubmitEventProposal;

final class SubmitEventProposalOutput
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $proposalId = null,
        public readonly ?string $error = null,
    ) {
    }
}
