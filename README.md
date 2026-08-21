<h1>
  Symfony Precognition
  <a href="https://github.com/FundraisingBox">
    <img
      src="https://github.com/FundraisingBox.png?size=128"
      alt="FundraisingBox"
      width="72"
      align="right"
    >
  </a>
</h1>

[![CI][ci-badge]][ci-workflow]
[![Latest release][release-badge]][latest-release]
[![License][license-badge]][license]

A Symfony bundle that validates a request without executing the controller
body. A _precognitive_ request runs the normal argument-resolution validation
or an explicitly annotated Symfony Form, then short-circuits before the
controller runs. This lets a client validate input — for example during live
form validation — without creating or mutating anything.

- validation passes → `204 No Content` + `Precognition-Success: true`
- validation fails → the application's normal validation error response
- every precognitive response carries `Precognition: true` and
  `Vary: Precognition`
- optional `Precognition-Validate-Only: a,b` limits which fields are reported

Routes are **opt-in by default**. Add `#[Precognitive]` to a controller method
or class that uses `#[MapRequestPayload]`, `#[MapQueryString]`,
`#[MapUploadedFile]`, or a custom value resolver. Symfony Forms opt in with
`#[PrecognitiveForm]`.

> [!IMPORTANT]
> Precognition only runs validation performed during argument resolution, plus
> explicitly annotated Symfony Forms. Validation and business rules inside the
> controller do not run. A `204` means that the input is structurally valid,
> not that the operation would succeed.

## Installation

```bash
composer require fundraisingbox/symfony-precognition
```

Symfony Flex enables the bundle automatically. Without Flex, add it to
`config/bundles.php`:

```php
return [
    // ...
    FundraisingBox\Precognition\PrecognitionBundle::class => ['all' => true],
];
```

## Quick start

Add `#[Precognitive]` to a route whose arguments are validated:

```php
use FundraisingBox\Precognition\Attribute\Precognitive;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

final class UserDto
{
    public function __construct(
        #[Assert\NotBlank]
        public string $name = '',
    ) {
    }
}

final class UserController
{
    #[Route('/users', methods: ['POST'])]
    #[Precognitive]
    public function create(#[MapRequestPayload] UserDto $user): Response
    {
        // Never runs for a precognitive request.
    }
}
```

Send the same request that would be submitted normally, with the
`Precognition` header:

```bash
curl -i -X POST https://example.test/users \
  -H 'Content-Type: application/json' \
  -H 'Precognition: true' \
  -d '{"name":"John"}'
```

A valid request receives:

```http
HTTP/1.1 204 No Content
Precognition: true
Precognition-Success: true
Vary: Precognition
```

An invalid `#[MapRequestPayload]` request receives the application's normal
validation response, usually `422`, with `Precognition: true` and
`Vary: Precognition` headers.

Clients should always check the `Precognition: true` **response** header. If a
route has not opted in, the request behaves as if the bundle were absent and
the controller runs normally.

## Configuration

Routes must opt in by default. To let every route answer precognitively, enable
global mode:

```yaml
# config/packages/precognition.yaml
precognition:
    allow_all_routes: true
```

Install `symfony/form` as well when using `#[PrecognitiveForm]`:

```bash
composer require symfony/form
```

## Documentation

- [How precognition works](docs/how-it-works.md)
- [Validating request payloads](docs/request-payloads.md)
- [Validating query strings](docs/query-strings.md)
- [Validating uploaded files](docs/file-uploads.md)
- [Validating Symfony Forms](docs/symfony-forms.md)
- [Validating selected fields](docs/validate-only.md)
- [Configuring CORS](docs/cors.md)
- [Laravel client compatibility](docs/laravel-client-compatibility.md)
- [Vanilla JavaScript frontend example](examples/vanilla-js)

## Prior art

This bundle ports the request/response protocol of
[Laravel Precognition](https://github.com/laravel/precognition), also described
for Rails by
[Inertia Precognition](https://inertia-rails.dev/guide/precognition).
The request and success protocol matches, but validation errors retain Symfony's
native status codes and response body.

> [!WARNING]
> The official Laravel Precognition frontend SDKs are not drop-in compatible
> because they expect Laravel's validation error shape. See the
> [compatibility guide](docs/laravel-client-compatibility.md) for details and a
> bridge recipe.

## Maintainers & Contribution

Maintained by [FundraisingBox](https://fundraisingbox.com) Developers. This not an official product by
FundraisingBox.

Contributions are welcome - please do not flood with vibe-coded PRs though.

## License

MIT. See [LICENSE](LICENSE).

[ci-badge]: https://github.com/FundraisingBox/symfony-precognition/actions/workflows/ci.yml/badge.svg
[ci-workflow]: https://github.com/FundraisingBox/symfony-precognition/actions/workflows/ci.yml
[release-badge]: https://img.shields.io/github/v/release/FundraisingBox/symfony-precognition
[latest-release]: https://github.com/FundraisingBox/symfony-precognition/releases/latest
[license-badge]: https://img.shields.io/badge/license-MIT-blue.svg
[license]: LICENSE

