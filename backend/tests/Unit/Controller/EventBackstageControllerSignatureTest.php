<?php

declare(strict_types=1);

namespace DaemsModule\Events\Tests\Unit\Controller;

use DaemsModule\Events\Controller\EventBackstageController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class EventBackstageControllerSignatureTest extends TestCase
{
    public function test_class_exists_and_is_final(): void
    {
        self::assertTrue(class_exists(EventBackstageController::class));
        $rc = new ReflectionClass(EventBackstageController::class);
        self::assertTrue($rc->isFinal());
    }

    /**
     * @return array<int, array{0: string}>
     */
    public static function methodNames(): array
    {
        return [
            ['listEvents'], ['createEvent'], ['updateEvent'], ['publishEvent'],
            ['archiveEvent'], ['listEventRegistrations'], ['removeEventRegistration'],
            ['statsEvents'], ['getEventWithTranslations'], ['updateEventTranslation'],
            ['listEventProposals'], ['approveEventProposal'], ['rejectEventProposal'],
            ['uploadEventImage'], ['deleteEventImage'],
        ];
    }

    /**
     * @dataProvider methodNames
     */
    public function test_method_exists_and_returns_response(string $methodName): void
    {
        $rc = new ReflectionClass(EventBackstageController::class);
        self::assertTrue($rc->hasMethod($methodName), "Method $methodName missing");
        $method = $rc->getMethod($methodName);
        self::assertTrue($method->isPublic(), "Method $methodName not public");
        $returnType = $method->getReturnType();
        self::assertNotNull($returnType, "Method $methodName has no return type");
        self::assertSame(\Daems\Infrastructure\Framework\Http\Response::class, (string) $returnType);
    }
}
