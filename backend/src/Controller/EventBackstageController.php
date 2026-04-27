<?php

declare(strict_types=1);

namespace DaemsModule\Events\Controller;

use DaemsModule\Events\Application\Backstage\ApproveEventProposal\ApproveEventProposal;
use DaemsModule\Events\Application\Backstage\ApproveEventProposal\ApproveEventProposalInput;
use DaemsModule\Events\Application\Backstage\ArchiveEvent\ArchiveEvent;
use DaemsModule\Events\Application\Backstage\ArchiveEvent\ArchiveEventInput;
use DaemsModule\Events\Application\Backstage\CreateEvent\CreateEvent;
use DaemsModule\Events\Application\Backstage\CreateEvent\CreateEventInput;
use DaemsModule\Events\Application\Backstage\DeleteEventImage\DeleteEventImage;
use DaemsModule\Events\Application\Backstage\DeleteEventImage\DeleteEventImageInput;
use DaemsModule\Events\Application\Backstage\Events\ListEventsStats\ListEventsStats;
use DaemsModule\Events\Application\Backstage\Events\ListEventsStats\ListEventsStatsInput;
use DaemsModule\Events\Application\Backstage\GetEventWithAllTranslations\GetEventWithAllTranslations;
use DaemsModule\Events\Application\Backstage\GetEventWithAllTranslations\GetEventWithAllTranslationsInput;
use DaemsModule\Events\Application\Backstage\ListEventProposalsForAdmin\ListEventProposalsForAdmin;
use DaemsModule\Events\Application\Backstage\ListEventProposalsForAdmin\ListEventProposalsForAdminInput;
use DaemsModule\Events\Application\Backstage\ListEventRegistrations\ListEventRegistrations;
use DaemsModule\Events\Application\Backstage\ListEventRegistrations\ListEventRegistrationsInput;
use DaemsModule\Events\Application\Backstage\ListEventsForAdmin\ListEventsForAdmin;
use DaemsModule\Events\Application\Backstage\ListEventsForAdmin\ListEventsForAdminInput;
use DaemsModule\Events\Application\Backstage\PublishEvent\PublishEvent;
use DaemsModule\Events\Application\Backstage\PublishEvent\PublishEventInput;
use DaemsModule\Events\Application\Backstage\RejectEventProposal\RejectEventProposal;
use DaemsModule\Events\Application\Backstage\RejectEventProposal\RejectEventProposalInput;
use DaemsModule\Events\Application\Backstage\UnregisterUserFromEvent\UnregisterUserFromEvent;
use DaemsModule\Events\Application\Backstage\UnregisterUserFromEvent\UnregisterUserFromEventInput;
use DaemsModule\Events\Application\Backstage\UpdateEvent\UpdateEvent;
use DaemsModule\Events\Application\Backstage\UpdateEvent\UpdateEventInput;
use DaemsModule\Events\Application\Backstage\UpdateEventTranslation\UpdateEventTranslation;
use DaemsModule\Events\Application\Backstage\UpdateEventTranslation\UpdateEventTranslationInput;
use DaemsModule\Events\Application\Backstage\UploadEventImage\UploadEventImage;
use DaemsModule\Events\Application\Backstage\UploadEventImage\UploadEventImageInput;
use Daems\Domain\Auth\ForbiddenException;
use Daems\Domain\Locale\InvalidLocaleException;
use Daems\Domain\Shared\NotFoundException;
use Daems\Domain\Shared\ValidationException;
use Daems\Domain\Storage\ImageStorageException;
use Daems\Domain\Tenant\Tenant;
use Daems\Infrastructure\Framework\Http\Request;
use Daems\Infrastructure\Framework\Http\Response;

final class EventBackstageController
{
    public function __construct(
        private readonly ListEventsForAdmin $listEventsForAdmin,
        private readonly CreateEvent $createEvent,
        private readonly UpdateEvent $updateEvent,
        private readonly PublishEvent $publishEvent,
        private readonly ArchiveEvent $archiveEvent,
        private readonly ListEventRegistrations $listEventRegistrations,
        private readonly UnregisterUserFromEvent $unregisterUserFromEvent,
        private readonly ListEventsStats $listEventsStats,
        private readonly GetEventWithAllTranslations $getEventWithAllTranslations,
        private readonly UpdateEventTranslation $updateEventTranslation,
        private readonly ListEventProposalsForAdmin $listEventProposals,
        private readonly ApproveEventProposal $approveEventProposal,
        private readonly RejectEventProposal $rejectEventProposal,
        private readonly UploadEventImage $uploadEventImageUseCase,
        private readonly DeleteEventImage $deleteEventImageUseCase,
    ) {}

    public function listEvents(Request $request): Response
    {
        $acting = $request->requireActingUser();
        try {
            $out = $this->listEventsForAdmin->execute(new ListEventsForAdminInput(
                $acting,
                $request->string('status'),
                $request->string('type'),
            ));
            return Response::json($out->toArray());
        } catch (ForbiddenException) {
            return Response::json(['error' => 'forbidden'], 403);
        }
    }

    public function createEvent(Request $request): Response
    {
        $acting = $request->requireActingUser();
        try {
            $out = $this->createEvent->execute(new CreateEventInput(
                $acting,
                (string) $request->string('title'),
                (string) $request->string('type'),
                (string) $request->string('event_date'),
                $request->string('event_time'),
                $request->string('location'),
                (bool) $request->input('is_online'),
                (string) $request->string('description'),
                (bool) $request->input('publish_immediately'),
            ));
            return Response::json(['data' => $out->toArray()], 201);
        } catch (ForbiddenException) {
            return Response::json(['error' => 'forbidden'], 403);
        } catch (ValidationException $e) {
            return Response::json(['error' => 'validation_failed', 'errors' => $e->fields()], 422);
        }
    }

    /** @param array<string, string> $params */
    public function updateEvent(Request $request, array $params): Response
    {
        $acting = $request->requireActingUser();
        $id = (string) ($params['id'] ?? '');
        try {
            $gallery = $request->input('gallery_json');
            $out = $this->updateEvent->execute(new UpdateEventInput(
                $acting, $id,
                $request->string('title'),
                $request->string('type'),
                $request->string('event_date'),
                $request->string('event_time'),
                $request->string('location'),
                $request->input('is_online') !== null ? (bool) $request->input('is_online') : null,
                $request->string('description'),
                $request->string('hero_image'),
                is_array($gallery) ? $gallery : null,
            ));
            return Response::json(['data' => $out->toArray()]);
        } catch (ForbiddenException) {
            return Response::json(['error' => 'forbidden'], 403);
        } catch (NotFoundException) {
            return Response::json(['error' => 'not_found'], 404);
        } catch (ValidationException $e) {
            return Response::json(['error' => 'validation_failed', 'errors' => $e->fields()], 422);
        }
    }

    /** @param array<string, string> $params */
    public function publishEvent(Request $request, array $params): Response
    {
        $acting = $request->requireActingUser();
        $id = (string) ($params['id'] ?? '');
        try {
            $this->publishEvent->execute(new PublishEventInput($acting, $id));
            return Response::json(['data' => ['id' => $id, 'status' => 'published']]);
        } catch (ForbiddenException) {
            return Response::json(['error' => 'forbidden'], 403);
        } catch (NotFoundException) {
            return Response::json(['error' => 'not_found'], 404);
        }
    }

    /** @param array<string, string> $params */
    public function archiveEvent(Request $request, array $params): Response
    {
        $acting = $request->requireActingUser();
        $id = (string) ($params['id'] ?? '');
        try {
            $this->archiveEvent->execute(new ArchiveEventInput($acting, $id));
            return Response::json(['data' => ['id' => $id, 'status' => 'archived']]);
        } catch (ForbiddenException) {
            return Response::json(['error' => 'forbidden'], 403);
        } catch (NotFoundException) {
            return Response::json(['error' => 'not_found'], 404);
        }
    }

    /** @param array<string, string> $params */
    public function listEventRegistrations(Request $request, array $params): Response
    {
        $acting = $request->requireActingUser();
        $id = (string) ($params['id'] ?? '');
        try {
            $out = $this->listEventRegistrations->execute(new ListEventRegistrationsInput($acting, $id));
            return Response::json($out->toArray());
        } catch (ForbiddenException) {
            return Response::json(['error' => 'forbidden'], 403);
        } catch (NotFoundException) {
            return Response::json(['error' => 'not_found'], 404);
        }
    }

    /** @param array<string, string> $params */
    public function removeEventRegistration(Request $request, array $params): Response
    {
        $acting = $request->requireActingUser();
        $eventId = (string) ($params['id'] ?? '');
        $userId  = (string) ($params['user_id'] ?? '');
        try {
            $this->unregisterUserFromEvent->execute(new UnregisterUserFromEventInput($acting, $eventId, $userId));
            return Response::json(null, 204);
        } catch (ForbiddenException) {
            return Response::json(['error' => 'forbidden'], 403);
        } catch (NotFoundException) {
            return Response::json(['error' => 'not_found'], 404);
        }
    }

    public function statsEvents(Request $request): Response
    {
        $acting = $request->requireActingUser();
        $tenant = $this->requireTenant($request);

        try {
            $out = $this->listEventsStats->execute(new ListEventsStatsInput(
                acting:   $acting,
                tenantId: $tenant->id,
            ));
        } catch (ForbiddenException) {
            return Response::forbidden('Admin only');
        }

        return Response::json(['data' => $out->stats]);
    }

    /** @param array<string, string> $params */
    public function getEventWithTranslations(Request $request, array $params): Response
    {
        $tenantId = $this->requireTenant($request)->id;
        $acting = $request->requireActingUser();
        $eventId = (string) ($params['id'] ?? '');

        try {
            $out = $this->getEventWithAllTranslations->execute(
                new GetEventWithAllTranslationsInput($tenantId, $eventId, $acting),
            );
        } catch (ForbiddenException) {
            return Response::json(['error' => 'forbidden'], 403);
        } catch (NotFoundException) {
            return Response::json(['error' => 'not_found'], 404);
        }
        return Response::json(['data' => $out->event]);
    }

    /** @param array<string, string> $params */
    public function updateEventTranslation(Request $request, array $params): Response
    {
        $tenantId = $this->requireTenant($request)->id;
        $acting = $request->requireActingUser();
        $eventId = (string) ($params['id'] ?? '');
        $localeRaw = (string) ($params['locale'] ?? '');
        $body = $request->all();

        try {
            $out = $this->updateEventTranslation->execute(
                new UpdateEventTranslationInput($tenantId, $eventId, $localeRaw, $body, $acting),
            );
        } catch (ForbiddenException) {
            return Response::json(['error' => 'forbidden'], 403);
        } catch (InvalidLocaleException) {
            return Response::json(['error' => 'invalid_locale'], 400);
        } catch (NotFoundException) {
            return Response::json(['error' => 'not_found'], 404);
        } catch (\DomainException $e) {
            return Response::json(['error' => $e->getMessage()], 400);
        }
        return Response::json(['data' => ['coverage' => $out->coverage]]);
    }

    public function listEventProposals(Request $request): Response
    {
        $tenantId = $this->requireTenant($request)->id;
        $acting = $request->requireActingUser();
        $status = $request->string('status');

        try {
            $out = $this->listEventProposals->execute(
                new ListEventProposalsForAdminInput(
                    $tenantId,
                    $acting,
                    $status !== null && $status !== '' ? $status : null,
                ),
            );
        } catch (ForbiddenException) {
            return Response::json(['error' => 'forbidden'], 403);
        }
        return Response::json(['data' => $out->proposals]);
    }

    /** @param array<string, string> $params */
    public function approveEventProposal(Request $request, array $params): Response
    {
        $acting = $request->requireActingUser();
        $proposalId = (string) ($params['id'] ?? '');
        $body = $request->all();
        $note = is_string($body['note'] ?? null) ? (string) $body['note'] : null;

        try {
            $out = $this->approveEventProposal->execute(
                new ApproveEventProposalInput($acting, $proposalId, $note),
            );
        } catch (ForbiddenException) {
            return Response::json(['error' => 'forbidden'], 403);
        } catch (NotFoundException) {
            return Response::json(['error' => 'not_found'], 404);
        } catch (ValidationException $e) {
            return Response::json(['error' => 'validation', 'details' => $e->fields()], 422);
        }
        return Response::json(['data' => ['event_id' => $out->eventId, 'slug' => $out->slug]], 201);
    }

    /** @param array<string, string> $params */
    public function rejectEventProposal(Request $request, array $params): Response
    {
        $acting = $request->requireActingUser();
        $proposalId = (string) ($params['id'] ?? '');
        $body = $request->all();
        $note = is_string($body['note'] ?? null) ? (string) $body['note'] : null;

        try {
            $this->rejectEventProposal->execute(
                new RejectEventProposalInput($acting, $proposalId, $note),
            );
        } catch (ForbiddenException) {
            return Response::json(['error' => 'forbidden'], 403);
        } catch (NotFoundException) {
            return Response::json(['error' => 'not_found'], 404);
        } catch (ValidationException $e) {
            return Response::json(['error' => 'validation', 'details' => $e->fields()], 422);
        }
        return Response::json(['data' => ['ok' => true]]);
    }

    /** @param array<string, string> $params */
    public function uploadEventImage(Request $request, array $params): Response
    {
        $acting = $request->requireActingUser();
        $id = (string) ($params['id'] ?? '');
        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || !isset($file['tmp_name'], $file['type'], $file['size']) || $file['error'] !== UPLOAD_ERR_OK) {
            return Response::json(['error' => 'validation_failed', 'errors' => ['file' => 'upload_error']], 422);
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            return Response::json(['error' => 'validation_failed', 'errors' => ['file' => 'too_large']], 422);
        }
        try {
            $out = $this->uploadEventImageUseCase->execute(new UploadEventImageInput(
                $acting, $id, (string) $file['tmp_name'], (string) $file['type'],
            ));
            return Response::json(['data' => $out->toArray()], 201);
        } catch (ForbiddenException) {
            return Response::json(['error' => 'forbidden'], 403);
        } catch (NotFoundException) {
            return Response::json(['error' => 'not_found'], 404);
        } catch (ValidationException $e) {
            return Response::json(['error' => 'validation_failed', 'errors' => $e->fields()], 422);
        } catch (ImageStorageException $e) {
            return Response::json(['error' => 'upload_failed', 'reason' => $e->getMessage()], 422);
        }
    }

    /** @param array<string, string> $params */
    public function deleteEventImage(Request $request, array $params): Response
    {
        $acting = $request->requireActingUser();
        $id = (string) ($params['id'] ?? '');
        $url = (string) $request->string('url');
        if ($url === '') {
            return Response::json(['error' => 'validation_failed', 'errors' => ['url' => 'required']], 422);
        }
        try {
            $this->deleteEventImageUseCase->execute(new DeleteEventImageInput($acting, $id, $url));
            return Response::json(null, 204);
        } catch (ForbiddenException) {
            return Response::json(['error' => 'forbidden'], 403);
        } catch (NotFoundException) {
            return Response::json(['error' => 'not_found'], 404);
        }
    }

    private function requireTenant(Request $request): Tenant
    {
        $tenant = $request->attribute('tenant');
        if (!$tenant instanceof Tenant) {
            throw new NotFoundException('unknown_tenant');
        }
        return $tenant;
    }
}
