# AGENTS.md

Guidance for coding agents working in this repository.

## Project overview

`konradmichalik/php-outbound` is the framework-agnostic core of Outbound: external APIs are declared as PHP interfaces with attributes, and generated proxies delegate to a runtime that adds resilience, caching and safety. Composer package type `library`, MIT.

- Namespace `KonradMichalik\Outbound\` maps to `src/`, tests use `KonradMichalik\Outbound\Tests\` in `tests/src/`
- PHP: `~8.2.0 || ~8.3.0 || ~8.4.0 || ~8.5.0` (Composer platform pinned to 8.2)
- Integrations live in their own packages: [`typo3-outbound`](https://github.com/konradmichalik/typo3-outbound) requires this package. Nothing here may depend on a framework

## Structure

- `src/Attribute/`: `#[Connection]`, `#[Resource]`, verbs, parameter roles, `#[Retry]`, `#[Timeout]`, `#[Cache]`, `#[RateLimit]`, `#[CircuitBreaker]`, `#[Paginate]`, `Auth\*` and more
- `src/Metadata/`: `MetadataFactory` (reflection, `extends` resolution, precedence method > resource > connection, validation), `Registry`, `Naming`
- `src/DependencyInjection/`: `OutboundCompilerPass` and `OutboundServices::discover()` register interfaces and build the registry at compile time
- `src/Proxy/`: proxy code generation and storage; generated code contains only reflection names and types, never attribute values
- `src/Runtime/`: `EndpointInvoker` -> `CallFactory` -> `CacheLayer` -> `Dispatcher` -> `ResultMapper` / `Paginator`
- `src/Context/`: `CallContext` (scope, settings, language), `ScopeRunner`, `ExecutionContext`, `ValueResolver`
- `src/Http/`, `src/Format/`, `src/Mapping/`, `src/Resilience/`, `src/Cache/`, `src/Auth/`, `src/Transport/`, `src/Support/`, `src/Value/`
- `src/Codegen/`, `src/Command/`: generators and Symfony Console commands
- `src/Testing/`: `MockTransport`, `OutboundTestTrait`, `SwitchableTransport`, public test API
- `src/Outbound.php`: composes the runtime without a container
- `tests/src/Fixtures/Petstore/`: Petstore interfaces (`KonradMichalik\OutboundPetstore\`), DTOs, mock transport, responses and the official OpenAPI document. `typo3-outbound` keeps a derived copy
- `tests/Demo/`: standalone Petstore frontend (`composer demo`)

Gotchas:
- PHP does not inherit attributes; the metadata factory walks the `extends` chain itself
- The Symfony loader keeps scanned interfaces only as `.abstract.<FQCN>` definitions tagged `container.excluded` (symfony/dependency-injection 7.4); the compiler pass walks `getDefinitions()`. Classes in `exclude` paths are skipped on purpose
- `NoPrivateNetworkHttpClient` throws synchronously and needs `symfony/http-foundation`
- Valinor runs in strict mode: list and array properties need a PHPDoc item type
- Keep exception codes unique (`1794700xxx`, shared with `typo3-outbound`); `ExceptionCodeUniquenessTest` fails on duplicates

## Development commands

```bash
composer install
composer lint        # composer normalize, editorconfig, php-cs-fixer (dry run)
composer fix         # the same with fixes
composer sca         # PHPStan level max, baseline in phpstan-baseline.neon
composer analyze     # composer-dependency-analyser
composer migration   # rector
composer test        # unit tests
composer test:live   # Petstore live, needs network
composer demo        # standalone Petstore frontend
```

`declare(strict_types=1)` everywhere, `final readonly` where sensible.

## Git workflow

- Commit format: `<type>: <description>` with type one of feat, fix, refactor, docs, test, chore, perf, ci
- No co-author trailers
