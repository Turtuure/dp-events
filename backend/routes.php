<?php

declare(strict_types=1);

use Daems\Infrastructure\Framework\Container\Container;
use Daems\Infrastructure\Framework\Http\Middleware\AuthMiddleware;
use Daems\Infrastructure\Framework\Http\Middleware\LocaleMiddleware;
use Daems\Infrastructure\Framework\Http\Middleware\TenantContextMiddleware;
use Daems\Infrastructure\Framework\Http\Request;
use Daems\Infrastructure\Framework\Http\Response;
use Daems\Infrastructure\Framework\Http\Router;
use DaemsModule\Events\Controller\EventBackstageController;
use DaemsModule\Events\Controller\EventController;

return static function (Router $router, Container $container): void {
    // Events — public reads (locale-aware)
    $router->get('/api/v1/events', static function (Request $req) use ($container): Response {
        return $container->make(EventController::class)->indexLocalized($req);
    }, [TenantContextMiddleware::class, LocaleMiddleware::class]);

    $router->get('/api/v1/events/{slug}', static function (Request $req, array $params) use ($container): Response {
        return $container->make(EventController::class)->showLocalized($req, $params);
    }, [TenantContextMiddleware::class, LocaleMiddleware::class]);

    // Events — legacy non-localized (kept for backward compat)
    $router->get('/api/v1/events-legacy', static function (Request $req) use ($container): Response {
        return $container->make(EventController::class)->index($req);
    }, [TenantContextMiddleware::class]);

    $router->get('/api/v1/events-legacy/{slug}', static function (Request $req, array $params) use ($container): Response {
        return $container->make(EventController::class)->show($req, $params);
    }, [TenantContextMiddleware::class]);

    // Event proposals — member submit
    $router->post('/api/v1/event-proposals', static function (Request $req) use ($container): Response {
        return $container->make(EventController::class)->submitProposal($req);
    }, [TenantContextMiddleware::class, LocaleMiddleware::class, AuthMiddleware::class]);

    // Events — protected mutations
    $router->post('/api/v1/events/{slug}/register', static function (Request $req, array $params) use ($container): Response {
        return $container->make(EventController::class)->register($req, $params);
    }, [TenantContextMiddleware::class, AuthMiddleware::class]);

    $router->post('/api/v1/events/{slug}/unregister', static function (Request $req, array $params) use ($container): Response {
        return $container->make(EventController::class)->unregister($req, $params);
    }, [TenantContextMiddleware::class, AuthMiddleware::class]);

    // Backstage — Events (admin)
    $router->get('/api/v1/backstage/events/stats', static function (Request $req) use ($container): Response {
        return $container->make(EventBackstageController::class)->statsEvents($req);
    }, [TenantContextMiddleware::class, AuthMiddleware::class]);

    $router->get('/api/v1/backstage/events', static function (Request $req) use ($container): Response {
        return $container->make(EventBackstageController::class)->listEvents($req);
    }, [TenantContextMiddleware::class, AuthMiddleware::class]);

    $router->post('/api/v1/backstage/events', static function (Request $req) use ($container): Response {
        return $container->make(EventBackstageController::class)->createEvent($req);
    }, [TenantContextMiddleware::class, AuthMiddleware::class]);

    $router->post('/api/v1/backstage/events/{id}', static function (Request $req, array $params) use ($container): Response {
        return $container->make(EventBackstageController::class)->updateEvent($req, $params);
    }, [TenantContextMiddleware::class, AuthMiddleware::class]);

    $router->post('/api/v1/backstage/events/{id}/publish', static function (Request $req, array $params) use ($container): Response {
        return $container->make(EventBackstageController::class)->publishEvent($req, $params);
    }, [TenantContextMiddleware::class, AuthMiddleware::class]);

    $router->post('/api/v1/backstage/events/{id}/archive', static function (Request $req, array $params) use ($container): Response {
        return $container->make(EventBackstageController::class)->archiveEvent($req, $params);
    }, [TenantContextMiddleware::class, AuthMiddleware::class]);

    $router->get('/api/v1/backstage/events/{id}/registrations', static function (Request $req, array $params) use ($container): Response {
        return $container->make(EventBackstageController::class)->listEventRegistrations($req, $params);
    }, [TenantContextMiddleware::class, AuthMiddleware::class]);

    $router->post('/api/v1/backstage/events/{id}/registrations/{user_id}/remove', static function (Request $req, array $params) use ($container): Response {
        return $container->make(EventBackstageController::class)->removeEventRegistration($req, $params);
    }, [TenantContextMiddleware::class, AuthMiddleware::class]);

    $router->post('/api/v1/backstage/events/{id}/images', static function (Request $req, array $params) use ($container): Response {
        return $container->make(EventBackstageController::class)->uploadEventImage($req, $params);
    }, [TenantContextMiddleware::class, AuthMiddleware::class]);

    $router->post('/api/v1/backstage/events/{id}/images/delete', static function (Request $req, array $params) use ($container): Response {
        return $container->make(EventBackstageController::class)->deleteEventImage($req, $params);
    }, [TenantContextMiddleware::class, AuthMiddleware::class]);

    $router->get('/api/v1/backstage/events/{id}/translations', static function (Request $req, array $params) use ($container): Response {
        return $container->make(EventBackstageController::class)->getEventWithTranslations($req, $params);
    }, [TenantContextMiddleware::class, AuthMiddleware::class]);

    $router->post('/api/v1/backstage/events/{id}/translations/{locale}', static function (Request $req, array $params) use ($container): Response {
        return $container->make(EventBackstageController::class)->updateEventTranslation($req, $params);
    }, [TenantContextMiddleware::class, AuthMiddleware::class]);

    // Backstage — event proposals (admin)
    $router->get('/api/v1/backstage/event-proposals', static function (Request $req) use ($container): Response {
        return $container->make(EventBackstageController::class)->listEventProposals($req);
    }, [TenantContextMiddleware::class, AuthMiddleware::class]);

    $router->post('/api/v1/backstage/event-proposals/{id}/approve', static function (Request $req, array $params) use ($container): Response {
        return $container->make(EventBackstageController::class)->approveEventProposal($req, $params);
    }, [TenantContextMiddleware::class, AuthMiddleware::class]);

    $router->post('/api/v1/backstage/event-proposals/{id}/reject', static function (Request $req, array $params) use ($container): Response {
        return $container->make(EventBackstageController::class)->rejectEventProposal($req, $params);
    }, [TenantContextMiddleware::class, AuthMiddleware::class]);
};
