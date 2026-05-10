<?php

declare(strict_types=1);

namespace DaemsModule\Events\Tests\Unit\Frontend\Backstage\Widgets;

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
            ['events.events_kpi',     WidgetCategory::Numbers, 1, MinRole::Admin, new EventsKpiWidget()],
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

    public function test_events_kpi_data_stub(): void
    {
        $w = new EventsKpiWidget();
        $d = $w->data(TenantId::generate());
        self::assertSame(0, $d['value']);
        self::assertSame(0.0, $d['change']);
    }

    public function test_upcoming_list_data_stub(): void
    {
        $w = new UpcomingEventsListWidget();
        $d = $w->data(TenantId::generate());
        self::assertSame([], $d['items']);
    }

    public function test_render_events_kpi_returns_html(): void
    {
        $w    = new EventsKpiWidget();
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

    private function fakeUser(): \Daems\Domain\User\User
    {
        $class = new \ReflectionClass(\Daems\Domain\User\User::class);
        return $class->newInstanceWithoutConstructor();
    }
}
