# Validating query strings

Add `#[Precognitive]` to a controller method that uses `#[MapQueryString]`:

```php
use FundraisingBox\Precognition\Attribute\Precognitive;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard', methods: ['GET'])]
#[Precognitive]
public function dashboard(#[MapQueryString] UserDto $userDto): Response
{
    // Never runs for a precognitive request.
}
```

Send the query normally with the `Precognition` header:

```bash
curl -i 'https://example.test/dashboard?firstName=&lastName=Smith&age=17' \
  -H 'Accept: application/json' \
  -H 'Precognition: true'
```

Symfony's `#[MapQueryString]` returns `404` for validation failures by default,
and precognitive requests retain that status. If the attribute specifies a
different `validationFailedStatusCode`, precognitive requests retain that
configured status as well.

## Return 422 for validation failures

Set `validationFailedStatusCode` on `#[MapQueryString]` to use the same
`422 Unprocessable Content` status as `#[MapRequestPayload]`:

```php
#[Route('/dashboard', methods: ['GET'])]
#[Precognitive]
public function dashboard(
    #[MapQueryString(
        validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
    )]
    UserDto $userDto,
): Response {
    // Never runs for a precognitive request.
}
```

This is Symfony's standard per-argument configuration and applies to normal and
precognitive requests alike.

Use [`Precognition-Validate-Only`](validate-only.md) to report violations for
selected DTO properties.
