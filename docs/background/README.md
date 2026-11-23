# Background

## Architecture

The compiler pass reads all API interfaces once and bakes their metadata into the container. A proxy generated on first use hands every call to the runtime:

`EndpointInvoker` → `CallFactory` (request, credentials) → `CacheLayer` (serve, revalidate) → `Dispatcher` (breaker, rate limit, budget, retries, size limit) → `ResultMapper` (typed return value)

## Security model

- HTTPS only; plain HTTP and private addresses only with `allowPrivateNetwork`, meant for intranet APIs
- Every URL, next-page links included, must match the allowed hosts; ports other than 80 and 443 must be listed as `host:port` or be the port of a base URI from code or an environment variable, never one from settings
- URLs from settings (base URI, token or login URL) need `allowedHosts`, enforced when the container is built
- Redirects are off: a 3xx answer throws `ApiRedirected` naming the target; the response size is limited during and after the download
- Path values are percent-encoded, dot segments are rejected, and generated proxy code contains no attribute values
- Secrets come from environment variables via `Secret`, never from settings
- Logs and exception messages mask credentials and sensitive query or header names; error bodies and argument values are never logged
- TLS verification can only be switched off in the Development context; cached entries are raw JSON and never unserialized into objects

## Performance

The runtime adds about 0.18 ms per uncached call and 0.12 ms per cache hit (`OverheadTest`, mock transport). Shared state is read sparingly, because with a shared backend every access is a query: the circuit breaker once per call, announced quotas at most every five seconds, an OAuth token once per process.

## Comparison

Saloon offers a mature ecosystem with request classes. php-outbound trades that for one method per endpoint, typed mapping without manual DTO code and web-safe retry defaults. Integrations bring per-scope credentials and the cache of their framework, see [`typo3-outbound`](https://github.com/konradmichalik/typo3-outbound). Symfony HttpClient provides the transport.
