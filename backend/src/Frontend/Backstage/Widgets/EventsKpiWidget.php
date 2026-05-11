<?php
declare(strict_types=1);

namespace DaemsModule\Events\Frontend\Backstage\Widgets;

use Daems\Application\Admin\GetAdminStats\GetAdminStats;
use Daems\Domain\Dashboard\MinRole;
use Daems\Domain\Dashboard\Widget;
use Daems\Domain\Dashboard\WidgetCategory;
use Daems\Domain\Dashboard\WidgetSpan;
use Daems\Domain\Tenant\TenantId;
use Daems\Domain\User\User;
use Daems\Frontend\I18n;
use Daems\Infrastructure\Dashboard\WidgetRenderer;

final class EventsKpiWidget extends Widget
{
    private const ICON = '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>';

    public function __construct(
        private readonly GetAdminStats $getAdminStats,
    ) {}

    public function id(): string             { return 'events.events_kpi'; }
    public function category(): WidgetCategory { return WidgetCategory::Numbers; }
    public function defaultSpan(): WidgetSpan  { return WidgetSpan::of(1); }
    public function minRole(): MinRole         { return MinRole::Admin; }
    public function module(): string           { return 'events'; }
    public function labelKey(): string         { return 'backstage.dashboard.widget.events_kpi.label'; }
    public function descriptionKey(): string   { return 'backstage.dashboard.widget.events_kpi.description'; }

    public function render(TenantId $tenantId, User $user): string
    {
        $d = $this->data($tenantId);
        return WidgetRenderer::kpi(
            value:         (int) $d['value'],
            change:        (float) $d['change'],
            label:         I18n::t($this->labelKey()),
            color:         'green',
            iconSvg:       self::ICON,
            sparklineId:   'spark-' . str_replace('.', '-', $this->id()),
            sparklineData: $d['sparkline'] ?? null,
        );
    }

    public function data(TenantId $tenantId): array
    {
        $stats = $this->getAdminStats->execute($tenantId);
        return [
            'value'     => $stats->upcomingEvents,
            'change'    => $stats->eventsChange,
            'sparkline' => $stats->eventsSparkline,
        ];
    }
}
