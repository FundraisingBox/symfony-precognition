# Validating request payloads

Add `#[Precognitive]` to a controller method that maps and validates a DTO with
`#[MapRequestPayload]`:

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
        // Never runs for a precognitive request.
    }
}
```

Send the payload with `Precognition: true`:

```bash
# Success -> 204 No Content
curl -i -X POST https://example.test/user \
  -H 'Content-Type: application/json' \
  -H 'Precognition: true' \
  -d '{"firstName":"John","lastName":"Smith","age":28}'

# Failure -> usually 422 with the application's normal error body
curl -i -X POST https://example.test/user \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -H 'Precognition: true' \
  -d '{"firstName":"","lastName":"Smith","age":17}'
```

The same approach works with a custom value resolver if the validation
exception it raises is, or wraps, Symfony's `ValidationFailedException`.

## Opt in a controller class

Place `#[Precognitive]` on a class to opt in all of its routes:

```php
use FundraisingBox\Precognition\Attribute\Precognitive;

#[Precognitive]
final class UserController
{
    // All routes on this controller allow precognitive requests.
}
```

Use [`Precognition-Validate-Only`](validate-only.md) to report violations for
selected DTO properties.
