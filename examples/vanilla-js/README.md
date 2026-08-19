# Vanilla JS example

A dependency-free frontend for the precognition bundle: no framework, no build
step. It validates each field on blur with a debounced precognitive request,
cancels superseded in-flight validations, and on submit runs one final
precognitive check before the real POST.

The form targets the bundle's functional-test fixtures:

- [`UserController::create()`](../../tests/Fixtures/UserController.php) —
  `POST /user`, opted in with `#[Precognitive]` and mapped via
  `#[MapRequestPayload]`.
- [`UserDto`](../../tests/Fixtures/UserDto.php) — `firstName` and
  `lastName` (`NotBlank`), `age` (`int`, `GreaterThan(18)`).

Because `UserDto::$age` is typed `int`, the client coerces the input value to a
number before sending — a JSON string would be rejected by the serializer as a
type error.

## Running it

1. Point a Symfony application that enables `PrecognitionBundle` at a
   `POST /user` endpoint like `UserController::create()` above: the action
   validates its payload via `#[MapRequestPayload]` and opts in with
   `#[Precognitive]`. Set `ENDPOINT` in [`precognition.js`](precognition.js)
   to its URL.
2. Serve this folder — for example `php -S localhost:5173` — and open
   `index.html`.

## CORS

If the frontend and the API are on different origins, the API must allow the
precognition request headers and expose the response headers:

```yaml
nelmio_cors:
    defaults:
        allow_headers: ['Content-Type', 'Precognition', 'Precognition-Validate-Only']
        expose_headers: ['Precognition', 'Precognition-Success']
```

## What to look at

`precognition.js` is organized top to bottom:

- **Payload** — builds the JSON body from the form with `FormData` and coerces
  `age` to a number to match `UserDto`.
- **Error rendering** — each error slot is keyed by the DTO property path
  (`data-error-for="age"`), so violations render next to their input.
- **Precognitive requests** — `requestValidation(only)` sends
  `Precognition: true` and, for single-field checks,
  `Precognition-Validate-Only`. One `AbortController` per validation scope
  cancels the in-flight request a newer one supersedes; `AbortError` is
  therefore ignored. A `204` means valid (returns `[]`), a `422` yields the
  violations.
- **Blur validation** — `focusout` schedules a debounced single-field check;
  the matching violation's `title` is rendered by `propertyPath`.
- **Submit** — one full precognitive check; only if it returns no violations
  does the real POST (without the `Precognition` header) run the controller.
