<?php

declare(strict_types=1);

/*
 * This file is part of the "php-outbound" Composer package.
 *
 * (c) 2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KonradMichalik\Outbound\Tests\Unit\Testing;

use KonradMichalik\Outbound\Http\RequestSpec;
use KonradMichalik\Outbound\Testing\{MockExpectation, MockTransport};
use KonradMichalik\Outbound\Transport\{Call, TransportOptions};
use KonradMichalik\OutboundPetstore\Api\{PetApi, StoreApi};
use LogicException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\Exception\TransportException;

/**
 * MockTransportTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(MockTransport::class)]
#[CoversClass(MockExpectation::class)]
final class MockTransportTest extends TestCase
{
    #[Test]
    public function aCallWithoutExpectationFailsInsteadOfReachingTheNetwork(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unexpected call to PetApi::find with {"petId":1}.');

        (new MockTransport())->request($this->call('PetApi::find', ['petId' => 1]));
    }

    #[Test]
    public function expectationsMatchOnTheEndpointAndASubsetOfTheArguments(): void
    {
        $transport = new MockTransport();
        $transport->on(PetApi::class, 'find', ['petId' => 2])->respondWith(200, 'two');
        $transport->on(PetApi::class, 'find')->respondWith(404, 'other');

        $matched = $transport->request($this->call('PetApi::find', ['petId' => 2, 'extra' => true]));
        $fallback = $transport->request($this->call('PetApi::find', ['petId' => 3]));

        self::assertSame(200, $matched->getStatusCode());
        self::assertSame('two', $matched->getContent());
        self::assertSame(404, $fallback->getStatusCode(), 'The generic expectation answers when the argument differs.');
    }

    #[Test]
    public function anExpectedArgumentMissingFromTheCallDoesNotMatch(): void
    {
        $transport = new MockTransport();
        $transport->on(PetApi::class, 'find', ['petId' => null])->respondWith();

        $this->expectException(LogicException::class);

        $transport->request($this->call('PetApi::find', []));
    }

    #[Test]
    public function responsesAreServedInOrderAndTheLastOneRepeats(): void
    {
        $transport = new MockTransport();
        $transport->on(PetApi::class, 'find')->respondWith(500)->respondWith(200, 'ok');
        $call = $this->call('PetApi::find', ['petId' => 1]);

        self::assertSame(500, $transport->request($call)->getStatusCode());
        self::assertSame(200, $transport->request($call)->getStatusCode());
        self::assertSame(200, $transport->request($call)->getStatusCode());
    }

    #[Test]
    public function jsonResponsesCarryTheContentTypeAndKeepCustomHeaders(): void
    {
        $transport = new MockTransport();
        $transport->on(PetApi::class, 'find')->respondWithJson(['id' => 1], 201, ['x-trace' => 'abc']);

        $response = $transport->request($this->call('PetApi::find', ['petId' => 1]));

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('{"id":1}', $response->getContent());
        self::assertSame(['application/json'], $response->getHeaders()['content-type']);
        self::assertSame(['abc'], $response->getHeaders()['x-trace']);
    }

    #[Test]
    public function fixturesAreServedAsJson(): void
    {
        $path = __DIR__.'/../../Fixtures/Petstore/openapi.yaml';
        $transport = new MockTransport();
        $transport->on(PetApi::class, 'find')->respondWithFixture($path, 202);

        $response = $transport->request($this->call('PetApi::find', ['petId' => 1]));

        self::assertSame(202, $response->getStatusCode());
        self::assertSame(file_get_contents($path), $response->getContent());
        self::assertSame(['application/json'], $response->getHeaders()['content-type']);
    }

    #[Test]
    public function anUnreadableFixtureIsReported(): void
    {
        $expectation = (new MockTransport())->on(PetApi::class, 'find');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is not readable');

        set_error_handler(static fn (): bool => true);

        try {
            $expectation->respondWithFixture('/nonexistent/fixture.json');
        } finally {
            restore_error_handler();
        }
    }

    #[Test]
    public function aFailureSurfacesAsTransportException(): void
    {
        $transport = new MockTransport();
        $transport->on(PetApi::class, 'find')->failWith('Boom');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Boom');

        $transport->request($this->call('PetApi::find', ['petId' => 1]))->getStatusCode();
    }

    #[Test]
    public function anExpectationWithoutResponseIsReported(): void
    {
        $transport = new MockTransport();
        $transport->on(PetApi::class, 'find');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No response configured for PetApi::find.');

        $transport->request($this->call('PetApi::find', []));
    }

    #[Test]
    public function keyedExpectationsAnswerCallsThatAreNoEndpoints(): void
    {
        $transport = new MockTransport();
        $transport->onKey('oauth-token')->respondWithJson(['access_token' => 'x']);

        $response = $transport->request($this->call('oauth-token', []));

        self::assertSame('{"access_token":"x"}', $response->getContent());
    }

    #[Test]
    public function callsAreRecordedAndCanBeFilteredPerEndpoint(): void
    {
        $transport = new MockTransport();
        $transport->on(PetApi::class, 'find')->respondWith();
        $transport->on(StoreApi::class, 'inventory')->respondWith();
        $transport->request($this->call('PetApi::find', ['petId' => 1]));
        $transport->request($this->call('PetApi::find', ['petId' => 2]));
        $transport->request($this->call('StoreApi::inventory', []));

        self::assertCount(3, $transport->calls());
        self::assertCount(2, $transport->calls(PetApi::class, 'find'));
        self::assertSame(1, $transport->count(StoreApi::class, 'inventory'));
        self::assertSame(0, $transport->count(PetApi::class, 'add'));
    }

    #[Test]
    public function resetForgetsExpectationsAndCalls(): void
    {
        $transport = new MockTransport();
        $transport->on(PetApi::class, 'find')->respondWith();
        $transport->request($this->call('PetApi::find', []));

        $transport->reset();

        self::assertSame([], $transport->calls());
        $this->expectException(LogicException::class);
        $transport->request($this->call('PetApi::find', []));
    }

    #[Test]
    public function keysUseTheShortInterfaceName(): void
    {
        self::assertSame('PetApi::find', MockTransport::key(PetApi::class, 'find'));
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function call(string $endpoint, array $arguments): Call
    {
        return new Call($endpoint, $arguments, new RequestSpec('GET', 'https://example.test/pets'), new TransportOptions(3.0, 1024));
    }
}
