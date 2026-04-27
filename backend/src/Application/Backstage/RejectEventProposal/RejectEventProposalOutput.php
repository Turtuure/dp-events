<?php

declare(strict_types=1);

namespace DaemsModule\Events\Application\Backstage\RejectEventProposal;

final class RejectEventProposalOutput
{
    public function __construct(public readonly bool $ok = true)
    {
    }
}
