<?php

declare(strict_types=1);

namespace DaemsModule\Events\Controller;

use DaemsModule\Events\Application\GetEvent\GetEvent;
use DaemsModule\Events\Application\GetEvent\GetEventInput;
use DaemsModule\Events\Application\GetEventBySlugForLocale\GetEventBySlugForLocale;
use DaemsModule\Events\Application\GetEventBySlugForLocale\GetEventBySlugForLocaleInput;
use DaemsModule\Events\Application\ListEvents\ListEvents;
use DaemsModule\Events\Application\ListEvents\ListEventsInput;
use DaemsModule\Events\Application\ListEventsForLocale\ListEventsForLocale;
use DaemsModule\Events\Application\ListEventsForLocale\ListEventsForLocaleInput;
use DaemsModule\Events\Application\RegisterForEvent\RegisterForEvent;
use DaemsModule\Events\Application\RegisterForEvent\RegisterForEventInput;
use DaemsModule\Events\Application\SubmitEventProposal\SubmitEventProposal;
use DaemsModule\Events\Application\SubmitEventProposal\SubmitEventProposalInput;
use DaemsModule\Events\Application\UnregisterFromEvent\UnregisterFromEvent;
use DaemsModule\Events\Application\UnregisterFromEvent\UnregisterFromEventInput;
use Daems\Domain\Locale\SupportedLocale;
use Daems\Domain\Shared\NotFoundException;
use Daems\Domain\Tenant\Tenant;
use Daems\Infrastructure\Framework\Http\Request;
use Daems\Infrastructure\Framework\Http\Response;

final class EventController
{
    public function __construct(
        private readonly ListEvents $listEvents,
        private readonly GetEvent $getEvent,
        private readonly RegisterForEvent $registerForEvent,
        private readonly UnregisterFromEvent $unregisterFromEvent,
        private readonly ListEventsForLocale $listEventsForLocale,
        private readonly GetEventBySlugForLocale $getEventBySlugForLocale,
        private readonly SubmitEventProposal $submitEventProposal,
    ) {}

    public function index(Request $request): Response
    {
        $tenantId = $this->requireTenant($request)->id;
        $type = $request->string('type');
        $output = $this->listEvents->execute(new ListEventsInput($tenantId, $type ?: null));
        return Response::json(['data' => $output->events]);
    }

    public function show(Request $request, array $params): Response
    {
        $tenantId = $this->requireTenant($request)->id;
        $userId = $request->string('user_id') ?: null;
        $output = $this->getEvent->execute(new GetEventInput($tenantId, $params['slug'], $userId));

        if ($output->event === null) {
            return Response::notFound('Event not found');
        }

        return Response::json(['data' => $output->event]);
    }

    private function requireTenant(Request $request): Tenant
    {
        $tenant = $request->attribute('tenant');
        if (!$tenant instanceof Tenant) {
            throw new NotFoundException('unknown_tenant');
        }
        return $tenant;
    }

    public function register(Request $request, array $params): Response
    {
        $acting = $request->requireActingUser();

        $output = $this->registerForEvent->execute(
            new RegisterForEventInput($acting, $params['slug']),
        );

        if ($output->error !== null && $output->error !== 'Already registered.') {
            return Response::notFound($output->error);
        }

        return Response::json(['data' => ['participant_count' => $output->participantCount]]);
    }

    public function unregister(Request $request, array $params): Response
    {
        $acting = $request->requireActingUser();

        $output = $this->unregisterFromEvent->execute(
            new UnregisterFromEventInput($acting, $params['slug']),
        );

        if ($output->error !== null) {
            return Response::notFound($output->error);
        }

        return Response::json(['data' => ['participant_count' => $output->participantCount]]);
    }

    public function indexLocalized(Request $request): Response
    {
        $tenantId = $this->requireTenant($request)->id;
        $locale = $this->requireLocale($request);
        $type = $request->string('type');
        $output = $this->listEventsForLocale->execute(
            new ListEventsForLocaleInput($tenantId, $locale, $type !== null && $type !== '' ? $type : null),
        );
        return Response::json(['data' => $output->events]);
    }

    /** @param array<string, string> $params */
    public function showLocalized(Request $request, array $params): Response
    {
        $tenantId = $this->requireTenant($request)->id;
        $locale = $this->requireLocale($request);
        $slug = (string) ($params['slug'] ?? '');
        $output = $this->getEventBySlugForLocale->execute(
            new GetEventBySlugForLocaleInput($tenantId, $locale, $slug),
        );
        if ($output->event === null) {
            return Response::notFound('Event not found');
        }
        return Response::json(['data' => $output->event]);
    }

    public function submitProposal(Request $request): Response
    {
        $acting = $request->requireActingUser();
        $locale = $this->requireLocale($request);

        $sourceLocaleRaw = $request->string('source_locale');
        $sourceLocale = $sourceLocaleRaw !== null && $sourceLocaleRaw !== ''
            ? $sourceLocaleRaw
            : $locale->value();

        $output = $this->submitEventProposal->execute(new SubmitEventProposalInput(
            acting: $acting,
            title: (string) $request->string('title', ''),
            eventDate: (string) $request->string('event_date', ''),
            eventTime: $request->string('event_time'),
            location: $request->string('location'),
            isOnline: (bool) $request->bool('is_online', false),
            description: (string) $request->string('description', ''),
            sourceLocale: $sourceLocale,
        ));

        if (!$output->ok) {
            return Response::json(['error' => $output->error ?? 'invalid_request'], 400);
        }
        return Response::json(['data' => ['id' => $output->proposalId]], 201);
    }

    private function requireLocale(Request $request): SupportedLocale
    {
        $locale = $request->attribute('locale');
        if (!$locale instanceof SupportedLocale) {
            // LocaleMiddleware missing on this route â€” default to content fallback.
            return SupportedLocale::contentFallback();
        }
        return $locale;
    }
}
