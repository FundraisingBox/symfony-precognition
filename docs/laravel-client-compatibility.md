# Laravel Precognition JS client compatibility

## Status

The official Laravel Precognition frontend SDKs — `laravel-precognition-vue`,
`laravel-precognition-react`, `laravel-precognition-alpine` and their framework
variants — are **not** drop-in compatible with this bundle. The wire protocol
(headers and status codes) matches, so the SDKs appear to work, but the JSON
body of a `422` response does not match what they parse, so **no field errors
are ever shown**.

This document explains the mismatch and how to bridge it. The bundle itself
ships no Laravel-compatible error formatter.

## What matches

The bundle already satisfies everything the SDK checks on the protocol level:

- **Response header `Precognition: true`.** The client throws
  `Did not receive a Precognition response ...` if it is missing. The bundle's
  response listener always sets it on precognitive responses.
- **Success detection.** The client treats a response as successful only when
  the status is `204` **and** `Precognition-Success: true` is present — exactly
  what the short-circuit flow produces.
- **Request format.** The client sends `Precognition: true` and, when validating
  a subset, `Precognition-Validate-Only` as a comma-joined list. The bundle's
  `PrecognitionContext` parses precisely that. (Wildcards such as `items.*.name`
  are expanded client-side, so the server only ever sees concrete paths.)

## What breaks

The client reads validation errors from `response.data.errors`: a map of field
name to an array of message strings, with dot-notation keys.

```json
{
  "message": "The username field is required. (and 1 more error)",
  "errors": {
    "username": ["The username field is required."],
    "address.city": ["The city field is required."]
  }
}
```

Symfony's default `422` (produced by `#[MapRequestPayload]` and rendered as
`application/problem+json`) instead ships a `violations` array of objects, with
no `errors` key at all.

```json
{
  "type": "https://symfony.com/errors/validation",
  "title": "Validation Failed",
  "violations": [
    { "propertyPath": "username", "title": "This value is too short." },
    { "propertyPath": "address.city", "title": "This value should not be blank." }
  ]
}
```

Because `response.data.errors` is `undefined`, the SDK marks the form invalid
but populates zero field messages. It fails silently — there is no console
error.

The mapping required to bridge the two shapes:

| Aspect                     | Symfony default                          | Laravel client expects                       |
| -------------------------- | ---------------------------------------- | -------------------------------------------- |
| Container                  | `violations: [{propertyPath, title}]`    | `errors: {field: [messages]}`                |
| Key syntax                 | `address.city`, also `items[0].name`     | dot-only: `items.0.name`                     |
| Multiple errors per field  | repeated array entries                   | grouped into one array                       |
| Top-level message          | `detail`                                 | `message` (first error + "(and N more ...)") |

## How to bridge it

### App-side listener (recommended)

Add an exception listener in your application that rewrites active precognitive
`422` responses into the Laravel shape. It runs after this bundle's
validate-only filter (priority `20`) and before Symfony's error renderer, so it
sees the already-filtered violation list.

```php
use FundraisingBox\Precognition\Http\PrecognitionContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
final class LaravelErrorResponseListener
{
    public function __construct(
        private readonly PrecognitionContext $precognition,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!$this->precognition->isActive($event->getRequest())) {
            return;
        }

        // Find the ValidationFailedException in the throwable chain
        // (#[MapRequestPayload] wraps it in an HttpException).
        $exception = $event->getThrowable();
        while (null !== $exception && !$exception instanceof ValidationFailedException) {
            $exception = $exception->getPrevious();
        }
        if (!$exception instanceof ValidationFailedException) {
            return;
        }

        $errors = [];
        foreach ($exception->getViolations() as $violation) {
            // Normalise items[0].name -> items.0.name
            $field = str_replace(['[', ']'], ['.', ''], $violation->getPropertyPath());
            $errors[$field][] = (string) $violation->getMessage();
        }

        $first = array_key_first($errors);
        $message = null === $first ? 'Validation failed.' : $errors[$first][0];
        $total = array_sum(array_map('count', $errors));
        if ($total > 1) {
            $message .= sprintf(' (and %d more errors)', $total - 1);
        }

        $event->setResponse(new JsonResponse(
            ['message' => $message, 'errors' => $errors],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        ));
    }
}
```

### Client-side interceptor (alternative)

If you cannot change the server, add an axios response interceptor that rewrites
`violations` into `errors` before the SDK sees the response. This keeps the
change per-frontend and needs no server code.

## Submit caveat

The SDK's `form.submit()` is a plain axios call **without** the precognition
header, so the bridge listener above (which only touches precognitive requests)
does not apply to it. A real submit that fails validation keeps Symfony's shape
unless the application formats those responses too.

## Future

If demand appears, the bridge could become an opt-in bundle feature (for
example an `error_format: laravel` option that registers such a listener). It is
deliberately out of scope for now — the bundle stays framework-idiomatic,
emitting Symfony's standard problem+json.
