# Instructions for AIs

- Ask clarifying questions when requirements are ambiguous, instead of assuming.
- Prioritize clear, readable, and maintainable code
- Use descriptively named methods and variables instead of comments

## Tech stack

- PHP 8
- Docker & Docker Compose
- PHPStan
- PHPUnit

The best practices for these must always be followed.
Assume the latest versions of all libraries and packages.

## Coding Preferences

- Apply object-oriented programming following SOLID principles.

### PHP

- Use strict types and declare them at the top of each file.
- Use PHP 8 features like property promotion, typed properties, attributes, union types, and named arguments.
- Always use constants for configuration values instead of strings.
- Use @throws annotations for methods that can throw exceptions.
- Implement proper error handling and logging: Handle expected exceptions with try-catch.
- Always create PHPUnit tests to ensure code reliability. 
  - Use the Arrange-Act-Assert pattern.
  - Aim for 100% code coverage.
  - Prefix test methods with `test` and do not use the Test attribute, but use attributes for everything else.
  - Use DataProviders for parameterized tests.
  - Use ValueObjects instead of mocks when possible.
  - Only use mocks when testing external services or when absolutely necessary.
  - Implement a testing pyramid, with a focus on unit tests. 
- Always use DateTimeImmutable for date and time handling.
