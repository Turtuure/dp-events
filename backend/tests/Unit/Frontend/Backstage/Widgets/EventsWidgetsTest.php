<?php

declare(strict_types=1);

namespace DaemsModule\Events\Tests\Unit\Frontend\Backstage\Widgets;

use Daems\Application\Admin\GetAdminStats\GetAdminStats;
use Daems\Domain\Admin\AdminStats;
use Daems\Domain\Admin\AdminStatsRepositoryInterface;
use Daems\Domain\Dashboard\MinRole;
use Daems\Domain\Dashboard\WidgetCategory;
use Daems\Domain\Tenant\TenantId;
use DaemsModule\Events\Frontend\Backstage\Widgets\EventsKpiWidget;
use DaemsModule\Events\Frontend\Backstage\Widgets\UpcomingEventsListWidget;
use PHPUnit\Framework\TestCase;

final class EventsWidgetsTest extends TestCase
{
    public function test_metadata_for_events_widgets(): void
    {
        $cases = [
            ['events.events_kpi',     WidgetCategory::Numbers, 1, MinRole::Admin, new EventsKpiWidget($this->fakeStats())],
            ['events.upcoming_list',  WidgetCategory::Lists,   2, MinRole::Admin, new UpcomingEventsListWidget()],
        ];

        foreach ($cases as [$id, $cat, $span, $role, $w]) {
            self::assertSame($id, $w->id());
            self::assertSame($cat, $w->category());
            self::assertSame($span, $w->defaultSpan()->value());
            self::assertSame($role, $w->minRole());
            self::assertSame('events', $w->module());
        }
    }

    public function test_events_kpi_data(): void
    {
        $w = new EventsKpiWidget($this->fakeStats());
        $d = $w->data(TenantId::generate());
        self::assertSame(3, $d['value']);
        self::assertSame(1.5, $d['change']);
    }

    public function test_upcoming_list_data_stub(): void
    {
        $w = new UpcomingEventsListWidget();
        $d = $w->data(TenantId::generate());
        self::assertSame([], $d['items']);
    }

    public function test_render_events_kpi_returns_html(): void
    {
        $w    = new EventsKpiWidget($this->fakeStats());
        $html = $w->render(TenantId::generate(), $this->fakeUser());
        self::assertNotSame('', $html);
        self::assertStringContainsString('card', $html);
    }

    public function test_render_upcoming_list_returns_html(): void
    {
        $w    = new UpcomingEventsListWidget();
        $html = $w->render(TenantId::generate(), $this->fakeUser());
        self::assertNotSame('', $html);
        self::assertStringContainsString('card', $html);
    }

    private function fakeStats(): GetAdminStats
    {
        $repo = new class implements AdminStatsRepositoryInterface {
            public function getStatsForTenant(TenantId $tenantId): AdminStats
            {
                return new AdminStats(
                    members: 0,
                    pendingApplications: 0,
                    upcomingEvents: 3,
                    activeProjects: 0,
                    membersSparkline: [],
                    applicationsSparkline: [],
                    eventsSparkline: [],
                    projectsSparkline: [],
                    forumSparkline: [],
                    insightsSparkline: [],
                    membersChange: 0.0,
                    applicationsChange: 0.0,
                    eventsChange: 1.5,
                    projectsChange: 0.0,
                    memberGrowth: ['labels' => [], 'series' => []],
                );
            }

            /** @return array{ labels: string[], series: int[] } */
            public function getMemberGrowthForTenant(string $period, TenantId $tenantId): array
            {
                return ['labels' => [], 'series' => []];
            }
        };

        return new GetAdminStats($repo);
    }

    private function fakeUser(): \Daems\Domain\User\User
    {
        $class = new \ReflectionClass(\Daems\Domain\User\User::class);
        return $class->newInstanceWithoutConstructor();
    }
}
