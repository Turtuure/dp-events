<?php

declare(strict_types=1);

use Daems\Domain\Shared\Clock;
use Daems\Domain\User\UserRepositoryInterface;
use Daems\Infrastructure\Framework\Container\Container;
use DaemsModule\Events\Application\Backstage\ApproveEventProposal\ApproveEventProposal;
use DaemsModule\Events\Application\Backstage\ArchiveEvent\ArchiveEvent;
use DaemsModule\Events\Application\Backstage\CreateEvent\CreateEvent;
use DaemsModule\Events\Application\Backstage\DeleteEventImage\DeleteEventImage;
use DaemsModule\Events\Application\Backstage\Events\ListEventsStats\ListEventsStats;
use DaemsModule\Events\Application\Backstage\GetEventWithAllTranslations\GetEventWithAllTranslations;
use DaemsModule\Events\Application\Backstage\ListEventProposalsForAdmin\ListEventProposalsForAdmin;
use DaemsModule\Events\Application\Backstage\ListEventRegistrations\ListEventRegistrations;
use DaemsModule\Events\Application\Backstage\ListEventsForAdmin\ListEventsForAdmin;
use DaemsModule\Events\Application\Backstage\PublishEvent\PublishEvent;
use DaemsModule\Events\Application\Backstage\RejectEventProposal\RejectEventProposal;
use DaemsModule\Events\Application\Backstage\UnregisterUserFromEvent\UnregisterUserFromEvent;
use DaemsModule\Events\Application\Backstage\UpdateEvent\UpdateEvent;
use DaemsModule\Events\Application\Backstage\UpdateEventTranslation\UpdateEventTranslation;
use DaemsModule\Events\Application\Backstage\UploadEventImage\UploadEventImage;
use DaemsModule\Events\Application\GetEvent\GetEvent;
use DaemsModule\Events\Application\GetEventBySlugForLocale\GetEventBySlugForLocale;
use DaemsModule\Events\Application\ListEvents\ListEvents;
use DaemsModule\Events\Application\ListEventsForLocale\ListEventsForLocale;
use DaemsModule\Events\Application\RegisterForEvent\RegisterForEvent;
use DaemsModule\Events\Application\SubmitEventProposal\SubmitEventProposal;
use DaemsModule\Events\Application\UnregisterFromEvent\UnregisterFromEvent;
use DaemsModule\Events\Controller\EventBackstageController;
use DaemsModule\Events\Controller\EventController;
use DaemsModule\Events\Domain\EventProposalRepositoryInterface;
use DaemsModule\Events\Domain\EventRepositoryInterface;
use DaemsModule\Events\Tests\Support\InMemoryEventProposalRepository;
use DaemsModule\Events\Tests\Support\InMemoryEventRepository;

return static function (Container $container): void {
    // Repositories — InMemory fakes (override production singletons)
    $container->singleton(EventRepositoryInterface::class,
        static fn() => new InMemoryEventRepository(),
    );
    $container->singleton(EventProposalRepositoryInterface::class,
        static fn() => new InMemoryEventProposalRepository(),
    );

    // Application/Event public use cases
    $container->bind(GetEvent::class,
        static fn(Container $c) => new GetEvent($c->make(EventRepositoryInterface::class)),
    );
    $container->bind(GetEventBySlugForLocale::class,
        static fn(Container $c) => new GetEventBySlugForLocale($c->make(EventRepositoryInterface::class)),
    );
    $container->bind(ListEvents::class,
        static fn(Container $c) => new ListEvents($c->make(EventRepositoryInterface::class)),
    );
    $container->bind(ListEventsForLocale::class,
        static fn(Container $c) => new ListEventsForLocale($c->make(EventRepositoryInterface::class)),
    );
    $container->bind(RegisterForEvent::class,
        static fn(Container $c) => new RegisterForEvent($c->make(EventRepositoryInterface::class)),
    );
    $container->bind(SubmitEventProposal::class,
        static fn(Container $c) => new SubmitEventProposal(
            $c->make(EventProposalRepositoryInterface::class),
            $c->make(UserRepositoryInterface::class),
        ),
    );
    $container->bind(UnregisterFromEvent::class,
        static fn(Container $c) => new UnregisterFromEvent($c->make(EventRepositoryInterface::class)),
    );

    // Application/Backstage admin use cases
    $container->bind(ApproveEventProposal::class,
        static fn(Container $c) => new ApproveEventProposal(
            $c->make(EventProposalRepositoryInterface::class),
            $c->make(EventRepositoryInterface::class),
            $c->make(Clock::class),
            $c->make(\Daems\Domain\Shared\IdGeneratorInterface::class),
        ),
    );
    $container->bind(ArchiveEvent::class,
        static fn(Container $c) => new ArchiveEvent($c->make(EventRepositoryInterface::class)),
    );
    $container->bind(CreateEvent::class,
        static fn(Container $c) => new CreateEvent(
            $c->make(EventRepositoryInterface::class),
            $c->make(\Daems\Domain\Shared\IdGeneratorInterface::class),
        ),
    );
    $container->bind(DeleteEventImage::class,
        static fn(Container $c) => new DeleteEventImage(
            $c->make(EventRepositoryInterface::class),
            $c->make(\Daems\Domain\Storage\ImageStorageInterface::class),
        ),
    );
    $container->bind(ListEventsStats::class,
        static fn(Container $c) => new ListEventsStats(
            $c->make(EventRepositoryInterface::class),
            $c->make(EventProposalRepositoryInterface::class),
        ),
    );
    $container->bind(GetEventWithAllTranslations::class,
        static fn(Container $c) => new GetEventWithAllTranslations($c->make(EventRepositoryInterface::class)),
    );
    $container->bind(ListEventProposalsForAdmin::class,
        static fn(Container $c) => new ListEventProposalsForAdmin($c->make(EventProposalRepositoryInterface::class)),
    );
    $container->bind(ListEventRegistrations::class,
        static fn(Container $c) => new ListEventRegistrations($c->make(EventRepositoryInterface::class)),
    );
    $container->bind(ListEventsForAdmin::class,
        static fn(Container $c) => new ListEventsForAdmin($c->make(EventRepositoryInterface::class)),
    );
    $container->bind(PublishEvent::class,
        static fn(Container $c) => new PublishEvent($c->make(EventRepositoryInterface::class)),
    );
    $container->bind(RejectEventProposal::class,
        static fn(Container $c) => new RejectEventProposal(
            $c->make(EventProposalRepositoryInterface::class),
            $c->make(Clock::class),
        ),
    );
    $container->bind(UnregisterUserFromEvent::class,
        static fn(Container $c) => new UnregisterUserFromEvent($c->make(EventRepositoryInterface::class)),
    );
    $container->bind(UpdateEvent::class,
        static fn(Container $c) => new UpdateEvent($c->make(EventRepositoryInterface::class)),
    );
    $container->bind(UpdateEventTranslation::class,
        static fn(Container $c) => new UpdateEventTranslation($c->make(EventRepositoryInterface::class)),
    );
    $container->bind(UploadEventImage::class,
        static fn(Container $c) => new UploadEventImage(
            $c->make(EventRepositoryInterface::class),
            $c->make(\Daems\Domain\Storage\ImageStorageInterface::class),
        ),
    );

    // Controllers
    $container->bind(EventController::class,
        static fn(Container $c) => new EventController(
            $c->make(ListEvents::class),
            $c->make(GetEvent::class),
            $c->make(RegisterForEvent::class),
            $c->make(UnregisterFromEvent::class),
            $c->make(ListEventsForLocale::class),
            $c->make(GetEventBySlugForLocale::class),
            $c->make(SubmitEventProposal::class),
        ),
    );
    $container->bind(EventBackstageController::class,
        static fn(Container $c) => new EventBackstageController(
            $c->make(ListEventsForAdmin::class),
            $c->make(CreateEvent::class),
            $c->make(UpdateEvent::class),
            $c->make(PublishEvent::class),
            $c->make(ArchiveEvent::class),
            $c->make(ListEventRegistrations::class),
            $c->make(UnregisterUserFromEvent::class),
            $c->make(ListEventsStats::class),
            $c->make(GetEventWithAllTranslations::class),
            $c->make(UpdateEventTranslation::class),
            $c->make(ListEventProposalsForAdmin::class),
            $c->make(ApproveEventProposal::class),
            $c->make(RejectEventProposal::class),
            $c->make(UploadEventImage::class),
            $c->make(DeleteEventImage::class),
        ),
    );
};
