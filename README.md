# Symfony Precognition

A Symfony bundle that validates a request without executing the controller
body. A _precognitive_ request runs the normal argument-resolution validation
(`#[MapRequestPayload]`, `#[MapQueryString]`, or a custom value resolver) or an
explicit Symfony Form via `#[PrecognitiveForm]`, then short-circuits before the
controller runs, so a client can validate input — for example live form
validation — without creating or mutating anything.

- validation passes → `204 No Content` + `Precognition-Success: true`
- validation fails → the application's normal validation error response
  (`422` for `#[MapRequestPayload]`; `404` for Symfony's default
  `#[MapQueryString]`)
- every precognitive response also carries `Precognition: true` and
  `Vary: Precognition`
- optional `Precognition-Validate-Only: a,b` limits which fields are reported

Routes are **opt-in by default**. Add `#[Precognitive]` to a controller method
or class to allow `Precognition: true` requests on routes whose validation runs
during argument resolution. `#[PrecognitiveForm]` is also an opt-in by itself.
Set `precognition.allow_all_routes: true` only if you want the previous global
mode where every route may answer precognitively.

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
                                    ▼
 kernel.controller       PrecognitionActivationListener
                         allows #[Precognitive], #[PrecognitiveForm],
                         or allow_all_routes
                                    │
                                    ▼
              custom resolvers validate here (before event)
                                    ▼
 kernel.controller_arguments  ┌────────────────────────────────────────┐
   MapRequestPayload validation │ runs here (this event, priority 0)     │
   PrecognitiveForm validation  │ runs after it (priority -32, opt-in)   │
   PrecognitionShortCircuit      │ runs after both (priority -64)         │
                                └───────────────────┬────────────────────┘
                          valid │                            │ invalid
                                ▼                            ▼
                  setController(no-op 204)        ValidationFailed / HttpException
                                │                            │
                                ▼                            ▼
                        controller skipped             kernel.exception
                                │            PrecognitionValidationListener (prio 20)
                                │            then the app's validation renderer
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
precognitive requests, the wrapped exception's original status is kept. That
means Symfony's `#[MapQueryString]` default `404` remains `404`, while
`#[MapRequestPayload]` validation failures normally remain `422`. The listener
then filters the standard `ConstraintViolationListInterface` in place, so the
downstream validation renderer automatically sees the filtered list.

> [!WARNING]
> **Only opted-in precognition requests short-circuit.** Sending
> `Precognition: true` to a route without `#[Precognitive]` or
> `#[PrecognitiveForm]` behaves as if the bundle were absent: the controller
> runs normally, no `Precognition-*` response headers are added, and validation
> failures keep the application's default handling. Clients should check for
> the `Precognition: true` **response** header before assuming the request was
> honoured.
>
> **Only argument-resolution validation runs.** The bundle reuses the
> validation performed while resolving controller arguments —
> `#[MapRequestPayload]`, `#[MapQueryString]`, or a custom value resolver that
> throws `ValidationFailedException` — plus explicitly annotated Symfony Forms.
> Other validation or business rules executed **inside the controller body** (or
> in a handler behind it) never run for a precognitive request. A `204`
> therefore means "the payload is structurally valid", not "this operation
> would succeed".

## Prior art

This bundle ports the request/response protocol of
[Laravel Precognition](https://github.com/laravel/precognition), also described
for Rails by [Inertia Precognition](https://inertia-rails.dev/guide/precognition).
The wire protocol — the `Precognition`, `Precognition-Success` and
`Precognition-Validate-Only` headers and the success response — matches, so the
same ideas and much of the same frontend behaviour apply. Validation error
status codes remain Symfony's native statuses for the resolver in use.

### Differences from Laravel Precognition

- **Opt-in.** Laravel opts in per route via the `HandlePrecognitiveRequests`
  middleware. This bundle matches that model with `#[Precognitive]`; setting
  `allow_all_routes: true` is the Symfony-specific escape hatch for global mode.
- **Rule vs. violation filtering.** Laravel filters the _rules_ before
  validating, so `Validate-Only` means only those rules execute. This bundle
  validates everything and filters the resulting _violations_ (post-validation
  filtering), so expensive constraints on non-requested fields still run.
- **Request API shape.** Laravel exposes `$request->isPrecognitive()` for
  conditional logic in form requests. Symfony requests cannot safely grow bundle
  methods, so inject `PrecognitionContext` and call
  `$precognition->isPrecognitive()` instead.
- **Error status and body shape.** Validation failures keep Symfony's native
  status for the resolver in use (`422` for `#[MapRequestPayload]`, `404` for
  default `#[MapQueryString]`). The body is whatever your application's
  renderer produces (Symfony `problem+json` by default), not Laravel's
  `{errors: {field: [...]}}` shape.

> [!WARNING]
> The official `laravel-precognition-vue` / `-react` / `-alpine` SDKs are **not**
> drop-in compatible. They read `response.data.errors` — which Symfony's
> validation error body does not contain — so forms display no field errors.
> Query-string validation can also return Symfony's default `404`. See
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

## Configuration

By default, routes must opt in with `#[Precognitive]` or `#[PrecognitiveForm]`.
To allow the header on every route, enable global mode:

```yaml
# config/packages/precognition.yaml
precognition:
    allow_all_routes: false # default
```

The only requirement for argument-resolution validation is that the validation
exception raised during argument resolution is (or wraps, as previous)
Symfony's standard `ValidationFailedException` — which is the case for
`#[MapRequestPayload]` and for any custom resolver that throws it.

Install `symfony/form` as well if you want to validate Symfony Forms
precognitively:

```bash
composer require symfony/form
```

## Usage

Given a DTO and controller that map and validate a payload:

```php
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use FundraisingBox\Precognition\Attribute\Precognitive;

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
    #[Precognitive]
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
use FundraisingBox\Precognition\Attribute\Precognitive;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;

#[Route('/dashboard', methods: ['GET'])]
#[Precognitive]
public function dashboard(#[MapQueryString] UserDto $userDto): Response
{
    // never runs for a precognitive request
}
```

```bash
curl -i 'https://example.test/dashboard?firstName=&lastName=Smith&age=17' \
  -H 'Accept: application/json' \
  -H 'Precognition: true'
# HTTP/1.1 404 Not Found
```

Symfony's `#[MapQueryString]` returns `404` for validation failures by default.
Precognitive requests keep that built-in status code. If your application
configures a different `validationFailedStatusCode` on `#[MapQueryString]`,
precognitive requests keep that configured status as well.

Uploaded files work through `#[MapUploadedFile]` as well:

```php
use FundraisingBox\Precognition\Attribute\Precognitive;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/user/picture', methods: ['PUT'])]
#[Precognitive]
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

You can also opt in a whole controller class:

```php
use FundraisingBox\Precognition\Attribute\Precognitive;

#[Precognitive]
final class UserController
{
    // All routes on this controller allow precognitive requests.
}
```

### Symfony Forms

Classic Symfony Forms validate in the controller body when
`$form->handleRequest($request)` submits the form. A global precognitive
short-circuit would otherwise skip that code and return a false `204`, so form
support is opt-in per endpoint:

```php
use FundraisingBox\Precognition\Attribute\PrecognitiveForm;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/task/new', methods: ['POST'])]
#[PrecognitiveForm(TaskType::class)]
public function new(Request $request): Response
{
    $form = $this->createForm(TaskType::class, new Task());
    $form->handleRequest($request);

    // never runs for a precognitive request
}
```

For a precognitive request, the bundle creates the annotated form type, disables
CSRF on that validation-only instance, submits the request payload, and converts
form errors into Symfony constraint violations. Normal, non-precognitive
submits still run your controller and keep the form's real CSRF behavior.

Form violation paths use field names without the root form prefix. For a form
named `task`, an invalid `task[task]` input is reported as `task`, and
`Precognition-Validate-Only: task` matches it.

`#[PrecognitiveForm]` implies precognitive opt-in. You do not need to add an
extra `#[Precognitive]` attribute to the same route.

### Detecting precognitive requests

Inject `PrecognitionContext` anywhere you need Laravel-like
`$request->isPrecognitive()` behavior:

```php
use FundraisingBox\Precognition\Http\PrecognitionContext;

final readonly class SomeService
{
    public function __construct(
        private PrecognitionContext $precognition,
    ) {
    }

    public function __invoke(): void
    {
        if ($this->precognition->isPrecognitive()) {
            // The client sent Precognition: true.
        }

        if ($this->precognition->isActive()) {
            // The current route opted in and precognition is being honoured.
        }
    }
}
```

### `Precognition-Validate-Only`

Field paths use Symfony property-path syntax and are matched by prefix, so
requesting `address` also keeps violations on `address.zipCode`. Dotted object
syntax (`address.zipCode`) and bracketed collection syntax (`[address][zipCode]`)
are normalised to the same path, so either form matches a requested field.

For DTO/query/file validation, send DTO property paths (`address.zipCode`), not
raw form field names. For `#[PrecognitiveForm]`, send rootless form field paths
(`task`, `category.name`).

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
| [`Attribute/Precognitive`](src/Attribute/Precognitive.php)                  | Method/class opt-in for argument-resolution validation       |
| [`Attribute/PrecognitiveForm`](src/Attribute/PrecognitiveForm.php)          | Method/class opt-in for Symfony Form validation              |
| [`Http/PrecognitionHeaders`](src/Http/PrecognitionHeaders.php)              | Header-name and value constants                              |
| [`Http/PrecognitionContext`](src/Http/PrecognitionContext.php)              | `isPrecognitive()`, active-route state, validate-only parsing |
| [`EventListener/PrecognitionActivationListener`](src/EventListener/PrecognitionActivationListener.php) | `kernel.controller` → route opt-in activation |
| [`EventListener/PrecognitionShortCircuitListener`](src/EventListener/PrecognitionShortCircuitListener.php) | `kernel.controller_arguments` → no-op `204` |
| [`EventListener/PrecognitionFormValidationListener`](src/EventListener/PrecognitionFormValidationListener.php) | `kernel.controller_arguments` → opt-in Symfony Form validation |
| [`EventListener/PrecognitionResponseListener`](src/EventListener/PrecognitionResponseListener.php) | `kernel.response` → protocol headers                |
| [`EventListener/PrecognitionValidationListener`](src/EventListener/PrecognitionValidationListener.php) | `kernel.exception` → `Precognition-Validate-Only` filtering |
| [`Form/FormErrorViolationMapper`](src/Form/FormErrorViolationMapper.php)  | Symfony Form errors → constraint violations          |
| [`Validation/ViolationPathFilter`](src/Validation/ViolationPathFilter.php)  | Field-path matching                                          |

## License

MIT. See [LICENSE](LICENSE).
