<?php
declare(strict_types=1);

namespace DaemsModule\Events\Frontend\Backstage\Widgets;

use Daems\Domain\Dashboard\MinRole;
use Daems\Domain\Dashboard\Widget;
use Daems\Domain\Dashboard\WidgetCategory;
use Daems\Domain\Dashboard\WidgetSpan;
use Daems\Domain\Tenant\TenantId;
use Daems\Domain\User\User;
use Daems\Frontend\I18n;
use Daems\Infrastructure\Dashboard\WidgetRenderer;

final class UpcomingEventsListWidget extends Widget
{
    public function __construct() {}

    public function id(): string             { return 'events.upcoming_list'; }
    public function category(): WidgetCategory { return WidgetCategory::Lists; }
    public function defaultSpan(): WidgetSpan  { return WidgetSpan::of(2); }
    public function minRole(): MinRole         { return MinRole::Admin; }
    public function module(): string           { return 'events'; }
    public function labelKey(): string         { return 'backstage.dashboard.widget.upcoming_list.label'; }
    public function descriptionKey(): string   { return 'backstage.dashboard.widget.upcoming_list.description'; }

    public function render(TenantId $tenantId, User $user): string
    {
        $d = $this->data($tenantId);
        return WidgetRenderer::list(
            I18n::t($this->labelKey()),
            array_values(array_map('strval', $d['items'])),
        );
    }

    public function data(TenantId $tenantId): array
    {
        // TODO(v1+): replace with real upcoming-events lookup (ListEventsForAdmin
        // filtered to upcoming, or a dedicated read model) in follow-up.
        return ['items' => []];
    }
}
