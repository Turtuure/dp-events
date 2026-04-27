<?php

declare(strict_types=1);

namespace DaemsModule\Events\Application\Backstage\RejectEventProposal;

use Daems\Domain\Auth\ActingUser;

final class RejectEventProposalInput
{
    public function __construct(
        public readonly ActingUser $acting,
        public readonly string $proposalId,
        public readonly ?string $note = null,
    ) {
    }
}
