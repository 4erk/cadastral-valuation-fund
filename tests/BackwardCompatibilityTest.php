<?php

declare(strict_types=1);

namespace Rosreestr\Cadastral\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Rosreestr\Cadastral\Client;
use Rosreestr\Cadastral\Valuation\Item;
use Rosreestr\Cadastral\Valuation\Link;
use Rosreestr\Cadastral\Valuation\Parser;
use Rosreestr\Cadastral\Valuation\Response;
use Rosreestr\Cadastral\Valuation\Value;

final class BackwardCompatibilityTest extends TestCase
{
    public function testAllLegacyPublicClassesRemainExtendable(): void
    {
        foreach ([Client::class, Parser::class, Item::class, Value::class, Link::class, Response::class] as $type) {
            self::assertFalse((new ReflectionClass($type))->isFinal(), $type . ' must remain extendable in v1');
        }
    }

    public function testDeprecatedLegacyEndpointConstantIsStillAvailable(): void
    {
        self::assertNotEmpty(Client::SEARCH_CADASTRAL_URL);
        self::assertStringContainsString('searchObjects', Client::SEARCH_CADASTRAL_URL);
    }

    public function testLegacyAddItemSignatureRemainsUntyped(): void
    {
        $argument = (new ReflectionMethod(Response::class, 'addItem'))->getParameters()[0];
        self::assertNull($argument->getType());
    }
}
