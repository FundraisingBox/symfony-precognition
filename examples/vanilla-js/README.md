# Vanilla JS example

A dependency-free frontend for the precognition bundle: no framework, no build
step. It validates each field on blur with a precognitive request, cancels
superseded in-flight validations, and on submit runs one final precognitive
check before the real POST.

The form maps to the same payload as the bundle's functional-test DTO:
`firstName`, `lastName`, and `age`.

## Running it

1. Point a Symfony application that enables `PrecognitionBundle` at a
   `POST /user` endpoint whose action validates its payload (for example via
   `#[MapRequestPayload]`) and opts in with `#[Precognitive]`. Set `ENDPOINT` in
   [`precognition.js`](precognition.js) to its URL.
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

- `precognite(only)` sends `Precognition: true` and, per field,
  `Precognition-Validate-Only`.
- An `AbortController` per field cancels the previous request when the value
  changes again; `AbortError` is ignored.
- A `204` clears the field error; a `422` renders the `title` of the matching
  violation by `propertyPath`.
