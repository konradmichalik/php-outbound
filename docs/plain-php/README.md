# Plain PHP

`Outbound::create()` composes the full runtime without a container: for scripts, tests and as the base of a framework integration.

```php
use KonradMichalik\Outbound\Outbound;

$outbound = Outbound::create([ContactsApi::class]);
$contacts = $outbound->api(ContactsApi::class);

$contact = $contacts->find(42);
```

Defaults are in-memory implementations, so cache and breaker state live for one process. Replace what you need with the `with*` methods:

| Seam | Interface | Default |
|------|-----------|---------|
| Transport | `Transport\Transport` | Symfony HttpClient |
| Response cache | `Cache\ResponseCache` | `InMemoryResponseCache` |
| Shared state (breaker, quotas, tokens) | `Resilience\StateStore` | `InMemoryStateStore` |
| Miss lock | `Cache\CacheLock` | `InMemoryCacheLock` |
| Tag flush after writes | `Cache\CacheInvalidator` | `ResponseCacheInvalidator` |
| Page cache tags | `Cache\CacheTagReporter` | `NullCacheTagReporter` |
| Scope, settings, language | `Context\CallContext` | `StaticCallContext` |
| Development or production | `Context\ExecutionContext` | `SapiExecutionContext` |
| Generated proxy code | `Proxy\ProxyStore` | `FileProxyStore` in the temp directory |

A `CallContext` provides the scope that keeps breaker, rate limit and token state apart, and the settings behind `Setting` and `Secret`:

```php
use KonradMichalik\Outbound\Context\StaticCallContext;

$outbound = $outbound->withCallContext(new StaticCallContext(
    scope: 'tenant-a',
    settings: ['crm.baseUri' => 'https://crm.example.com', 'crm.token' => 'env:CRM_TOKEN'],
));
```

## Dependency injection

`DependencyInjection\OutboundCompilerPass` registers every interface with `#[Connection]` or `#[Resource]` found by the resource scan of a Symfony container, and `OutboundServices::discover()` covers interfaces outside it. Invalid declarations fail the container build.

## Commands

The Symfony Console commands in `Command\` (`outbound:debug`, `outbound:lint`, `outbound:call`, `outbound:health`, `outbound:cache:flush`, `outbound:make:dto`, `outbound:generate`) are plain `Command` classes. Register them in your application with the services they need; each explains its arguments with `--help`. `outbound:call --scope=...` needs a `Context\ScopeRunner`.

## Testing

`MockTransport` answers by endpoint and arguments, never by URL; unexpected calls fail:

```php
$mock = new MockTransport();
$mock->on(ContactsApi::class, 'find', ['id' => 42])->respondWithFixture(__DIR__.'/contact.json');

$contacts = Outbound::create([ContactsApi::class])->withTransport($mock)->api(ContactsApi::class);
```

`OutboundTestTrait` routes all outbound calls of a test with a booted container to a `MockTransport`. The integration decorates the transport with `SwitchableTransport` in its test context.

## Demo

`composer demo` serves a Petstore frontend on `localhost:8080` without any framework. Append `?mock=1` to answer from fixture responses instead of the live API.
