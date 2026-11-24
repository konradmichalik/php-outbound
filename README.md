<div align="center">

![icon](icon.png)

# `php-outbound`

[![Supported PHP Versions](https://img.shields.io/packagist/dependency-v/konradmichalik/php-outbound/php?logo=php)](https://packagist.org/packages/konradmichalik/php-outbound)
[![CGL](https://img.shields.io/github/actions/workflow/status/konradmichalik/php-outbound/cgl.yml?label=cgl&logo=github)](https://github.com/konradmichalik/php-outbound/actions/workflows/cgl.yml)
[![Tests](https://img.shields.io/github/actions/workflow/status/konradmichalik/php-outbound/tests.yml?label=tests&logo=github)](https://github.com/konradmichalik/php-outbound/actions/workflows/tests.yml)
[![Coverage](https://img.shields.io/coverallsCoverage/github/konradmichalik/php-outbound?logo=coveralls)](https://coveralls.io/github/konradmichalik/php-outbound)
[![License](https://img.shields.io/packagist/l/konradmichalik/php-outbound)](LICENSE)

</div>

Declarative API clients for PHP: describe **external APIs as interfaces with attributes** and get the implementation generated. Your code calls methods and receives typed objects, without HTTP code of its own.

> [!NOTE]
> The goal is to replace the hand-written service wrapper per API: you declare what an API offers, and timeouts, retries, caching and security come as safe defaults.
>
> *Outbound* as in outgoing requests: your application calls the API.

> [!WARNING]
> This package is at an early stage. Attributes and APIs may still change before 1.0.

## ✨ Features

- [**Less code per API**](docs/endpoints/README.md): one interface method per endpoint and typed DTOs instead of request classes and HTTP plumbing
- [**Fast**](docs/features/README.md#caching): cached responses, revalidation with ETags and a miss lock
- [**Keeps working when an API fails**](docs/features/README.md#timeouts-and-retries): short timeouts, safe retries, a circuit breaker and, if you allow it, stale data to bridge an outage
- [**Secure by default**](docs/background/README.md#security-model): HTTPS only, allowed hosts, private networks blocked, secrets from the environment and redacted logs
- [**Quick start from OpenAPI**](docs/plain-php/README.md#commands): interfaces and DTOs generated from an OpenAPI document or a sample response
- [**Tests without network**](docs/plain-php/README.md#testing): mocks by endpoint instead of URL

## 🔥 Installation

Requires PHP 8.2+ and Symfony components 7.4.

```bash
composer require konradmichalik/php-outbound
```

Using TYPO3? Install [`konradmichalik/typo3-outbound`](https://github.com/konradmichalik/typo3-outbound), which builds on this package.

## 🚀 Quick start

Declare the API as an interface carrying `#[Connection]` and annotate a method with an HTTP verb:

```php
use KonradMichalik\Outbound\Attribute as Outbound;

#[Outbound\Connection(baseUri: 'https://api.weather.example.com')]
interface WeatherApi
{
    /** @return array{city: string, temperature: float} */
    #[Outbound\Get('/forecast/{city}')]
    public function forecast(string $city): array;
}
```

Compose the runtime and call the method:

```php
use KonradMichalik\Outbound\Outbound;

$weather = Outbound::create([WeatherApi::class])->api(WeatherApi::class);

$temperature = $weather->forecast('Dresden')['temperature'];
```

`forecast('Dresden')` sends `GET https://api.weather.example.com/forecast/Dresden`, checks the JSON response against the declared shape and returns it. For larger responses, return a DTO class instead of an array shape.

Everything else is opt-in. A larger API splits into resources, takes its base URI and secret from the runtime configuration, maps responses to DTOs, retries, caches and maps a 404 to `null`, all declared with attributes:

```php
use KonradMichalik\Outbound\Value\{Secret, Setting};

#[Outbound\Connection(baseUri: new Setting('crm.baseUri'), allowedHosts: ['crm.example.com'])]
#[Outbound\Auth\Bearer(token: new Secret('crm.token'))]
#[Outbound\Retry(maxRetries: 2)]
interface CrmApi {}

#[Outbound\Resource('/contacts')]
interface ContactsApi extends CrmApi
{
    #[Outbound\Get('/{id}')]
    #[Outbound\Cache(lifetime: 600, tags: ['crm_contact_{id}'], staleOnError: 3600)]
    #[Outbound\OnStatus(404, returns: null)]
    public function find(int $id): ?ContactDto;
}
```

See [Defining endpoints](docs/endpoints/README.md) for connections, resources, parameters and return types, and [Features](docs/features/README.md) for every opt-in attribute.

## 📚 Documentation

Full documentation lives in [`docs/`](docs/README.md): [defining endpoints](docs/endpoints/README.md), [features](docs/features/README.md), [plain PHP usage](docs/plain-php/README.md) and [background](docs/background/README.md).

## 🧑‍💻 Contributing

Please have a look at [`CONTRIBUTING.md`](CONTRIBUTING.md).

## ⭐ License

This project is licensed under the [MIT License](LICENSE).
