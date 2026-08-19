# Validating selected fields

Send `Precognition-Validate-Only` with a comma-separated list to report
violations for selected fields:

```bash
curl -i -X POST https://example.test/user \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -H 'Precognition: true' \
  -H 'Precognition-Validate-Only: firstName,age' \
  -d '{"firstName":"","lastName":"","age":17}'
```

The bundle still runs every validation constraint and then filters the
resulting violations. Constraints on fields not listed in the header therefore
still execute, including expensive constraints.

Field paths use Symfony property-path syntax and are matched by prefix.
Requesting `address` also retains violations on `address.zipCode`. Dotted object
syntax (`address.zipCode`) and bracketed collection syntax
(`[address][zipCode]`) are normalized to the same path, so either form matches a
requested field.

For DTO, query, and file validation, send DTO property paths such as
`address.zipCode`, not raw form field names. For `#[PrecognitiveForm]`, send
rootless form field paths such as `task` or `category.name`.

Unlike Laravel Precognition, this is violation filtering rather than rule
filtering. See [how precognition works](how-it-works.md#validation-failures) for
where filtering occurs in the request lifecycle.
