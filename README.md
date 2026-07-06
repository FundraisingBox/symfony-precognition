# Symfony Precognition

A Symfony bundle that validates a request without executing the controller
body. A _precognitive_ request runs the normal argument-resolution validation
(`#[MapRequestPayload]`, `#[MapQueryString]`, or a custom value resolver), then
short-circuits before the controller runs, so a client can validate input — for
example live form validation — without creating or mutating anything.

- validation passes → `204 No Content` + `Precognition-Success: true`
- validation fails → the application's normal validation error response (`422`)
- every precognitive response also carries `Precognition: true` and
  `Vary: Precognition`
- optional `Precognition-Validate-Only: a,b` limits which fields are reported

The behaviour is **global and header-driven**: any endpoint can be used
precognitively by sending `Precognition: true`. There is no per-endpoint opt-in.

## How it works

Validation runs during argument resolution, before the controller body. On
failure it throws — custom resolvers throw from argument resolution;
`RequestPayloadValueResolver` (`#[MapRequestPayload]`) throws from its
`kernel.controller_arguments` subscriber — and the exception flows to
`kernel.exception`, producing the application's normal error response. On
success the short-circuit listener replaces the controller with a no-op `204`.

```
                          Precognition: true request
                                    │
              custom resolvers validate here (before event)
                                    ▼
 kernel.controller_arguments  ┌────────────────────────────────────────┐
   MapRequestPayload validation │ runs here (this event, priority 0)     │
   PrecognitionShortCircuit      │ runs AFTER it (priority -64)           │
                                └───────────────────┬────────────────────┘
                          valid │                            │ invalid
                                ▼                            ▼
                  setController(no-op 204)        ValidationFailed / HttpException
                                │                            │
                                ▼                            ▼
                        controller skipped             kernel.exception
                                │            PrecognitionValidationListener (prio 20)
                                │            then the app's 422 renderer
                                └──────────────┬─────────────┘
                                               ▼
                                          kernel.response
                       PrecognitionResponseListener adds headers:
                       Precognition: true, Vary: Precognition,
                       Precognition-Success: true (on 204 only)
```

The `Precognition-Validate-Only` filtering happens at the exception stage:
Symfony's validator dispatches no events, so there is no validation-time hook to
filter violations. The listener keys off Symfony's standard
`ValidationFailedException` — found either as the thrown exception (custom
resolvers) or in the `getPrevious()` chain (`#[MapRequestPayload]`,
`#[MapQueryString]` and `#[MapUploadedFile]` wrap it in an `HttpException`). For
precognitive requests, wrapped validation failures are normalised to `422` before
rendering; this keeps Laravel-compatible response semantics even though
Symfony's `#[MapQueryString]` defaults to `404`. The listener then filters the
standard `ConstraintViolationListInterface` in place, so the downstream `422`
renderer automatically sees the filtered list.

> [!WARNING]
> **Only argument-resolution validation runs.** The bundle reuses the
> validation performed while resolving controller arguments —
> `#[MapRequestPayload]`, `#[MapQueryString]`, or a custom value resolver that
> throws `ValidationFailedException`. Validation or business rules executed
> **inside the controller body** (or in a handler behind it) never run for a
> precognitive request. A `204` therefore means "the payload is structurally
> valid", not "this operation would succeed".

## Prior art

This bundle ports the request/response protocol of
[Laravel Precognition](https://github.com/laravel/precognition), also described
for Rails by [Inertia Precognition](https://inertia-rails.dev/guide/precognition).
The wire protocol — the `Precognition`, `Precognition-Success` and
`Precognition-Validate-Only` headers and the `204`/`422` status codes — matches,
so the same ideas and much of the same frontend behaviour apply.

### Differences from Laravel Precognition

- **Opt-in.** Laravel opts in per route via the `HandlePrecognitiveRequests`
  middleware. This bundle is global and purely header-driven: any endpoint whose
  validation runs during argument resolution can be used precognitively.
- **Rule vs. violation filtering.** Laravel filters the _rules_ before
  validating, so `Validate-Only` means only those rules execute. This bundle
  validates everything and filters the resulting _violations_ (post-validation
  filtering), so expensive constraints on non-requested fields still run.
- **No per-request hook.** Laravel exposes `$request->isPrecognitive()` for
  conditional logic in form requests. The equivalent here is
  `PrecognitionRequest::isPrecognitive()`, but there is no per-endpoint hook or
  side-effect API — the bundle is transparent.
- **Error body shape.** The `422` body is whatever your application's renderer
  produces (Symfony `problem+json` by default), not Laravel's
  `{errors: {field: [...]}}` shape.

> [!WARNING]
> The official `laravel-precognition-vue` / `-react` / `-alpine` SDKs are **not**
> drop-in compatible. Headers and status codes match, so they appear to work,
> but they read `response.data.errors` — which Symfony's `422` body does not
> contain — so forms display no field errors. See
> [docs/laravel-client-compatibility.md](docs/laravel-client-compatibility.md)
> for the details and a bridge recipe.

## Installation

```bash
composer require fundraisingbox/symfony-precognition
```

Enable the bundle (Symfony Flex does this automatically; otherwise add it to
`config/bundles.php`):

```php
return [
    // ...
    FundraisingBox\Precognition\PrecognitionBundle::class => ['all' => true],
];
```

There is nothing to configure and nothing to wire per endpoint. The only
requirement is that the validation exception raised during argument resolution
is (or wraps, as previous) Symfony's standard `ValidationFailedException` —
which is the case for `#[MapRequestPayload]` and for any custom resolver that
throws it.

## Usage

Given a DTO and controller that map and validate a payload:

```php
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

final class UserDto
{
    public function __construct(
        #[Assert\NotBlank]
        public string $firstName = '',
        #[Assert\NotBlank]
        public string $lastName = '',
        #[Assert\GreaterThan(18)]
        public int $age = 0,
    ) {
    }
}

final class UserController
{
    #[Route('/user', methods: ['POST'])]
    public function create(#[MapRequestPayload] UserDto $userDto): Response
    {
        // never runs for a precognitive request
    }
}
```

a client validates it precognitively with the `Precognition` header:

```bash
# Success -> 204 No Content
curl -i -X POST https://example.test/user \
  -H 'Content-Type: application/json' \
  -H 'Precognition: true' \
  -d '{"firstName":"John","lastName":"Smith","age":28}'
# HTTP/1.1 204 No Content
# Precognition: true
# Precognition-Success: true
# Vary: Precognition

# Failure -> 422 with the app's normal error body
curl -i -X POST https://example.test/user \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -H 'Precognition: true' \
  -d '{"firstName":"","lastName":"Smith","age":17}'
# HTTP/1.1 422 Unprocessable Content
# Precognition: true
# Vary: Precognition

# Validate only selected fields
curl -i -X POST https://example.test/user \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -H 'Precognition: true' \
  -H 'Precognition-Validate-Only: firstName,age' \
  -d '{ ... }'
```

The same protocol applies to query-string validation:

```php
use Symfony\Component\HttpKernel\Attribute\MapQueryString;

#[Route('/dashboard', methods: ['GET'])]
public function dashboard(#[MapQueryString] UserDto $userDto): Response
{
    // never runs for a precognitive request
}
```

```bash
curl -i 'https://example.test/dashboard?firstName=&lastName=Smith&age=17' \
  -H 'Accept: application/json' \
  -H 'Precognition: true'
# HTTP/1.1 422 Unprocessable Content
```

Symfony's `#[MapQueryString]` returns `404` for validation failures by default.
For precognitive requests only, this bundle rewrites validation failures to
`422` so query-string validation follows the same wire protocol as payload and
file validation. Non-precognitive requests keep Symfony's configured status.

Uploaded files work through `#[MapUploadedFile]` as well:

```php
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/user/picture', methods: ['PUT'])]
public function changePicture(
    #[MapUploadedFile([
        new Assert\File(mimeTypes: ['image/png', 'image/jpeg']),
    ])]
    UploadedFile $picture,
): Response {
    // never runs for a precognitive request
}
```

If a file endpoint should be usable precognitively without re-uploading the
file, make the argument nullable, for example `?UploadedFile $picture = null`.
A missing non-nullable file is rejected by Symfony with `422` before it creates a
validation violation list, so `Precognition-Validate-Only` has nothing to filter.

### `Precognition-Validate-Only`

Field paths use Symfony property-path syntax and are matched by prefix, so
requesting `address` also keeps violations on `address.zipCode`. Dotted object
syntax (`address.zipCode`) and bracketed collection syntax (`[address][zipCode]`)
are normalised to the same path, so either form matches a requested field.

Send DTO property paths (`address.zipCode`), not raw form field names.

## CORS

For a cross-origin frontend, allow the request headers and expose the response
headers. With [nelmio/cors-bundle](https://github.com/nelmio/NelmioCorsBundle):

```yaml
nelmio_cors:
    defaults:
        allow_headers: ['Content-Type', 'Precognition', 'Precognition-Validate-Only']
        expose_headers: ['Precognition', 'Precognition-Success']
```

## Frontend example

A dependency-free example is in [`examples/vanilla-js`](examples/vanilla-js): a
small form that validates each field on blur, cancels superseded in-flight
validations, and renders the returned violations.

## Components

| Class                                                                       | Responsibility                                               |
| --------------------------------------------------------------------------- | ------------------------------------------------------------ |
| [`Http/PrecognitionHeaders`](src/Http/PrecognitionHeaders.php)              | Header-name and value constants                              |
| [`Http/PrecognitionRequest`](src/Http/PrecognitionRequest.php)              | `isPrecognitive()`, `validateOnly()`                         |
| [`EventListener/PrecognitionShortCircuitListener`](src/EventListener/PrecognitionShortCircuitListener.php) | `kernel.controller_arguments` → no-op `204` |
| [`EventListener/PrecognitionResponseListener`](src/EventListener/PrecognitionResponseListener.php) | `kernel.response` → protocol headers                |
| [`EventListener/PrecognitionValidationListener`](src/EventListener/PrecognitionValidationListener.php) | `kernel.exception` → validation status normalisation and `Precognition-Validate-Only` filtering |
| [`Validation/ViolationPathFilter`](src/Validation/ViolationPathFilter.php)  | Field-path matching                                          |

## License

MIT. See [LICENSE](LICENSE).
