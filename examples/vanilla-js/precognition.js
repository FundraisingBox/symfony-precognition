// Dependency-free client for the Symfony Precognition bundle.
//
// The backend is the bundle's functional-test fixture:
//   - UserController::create() — POST /user, #[Precognitive] + #[MapRequestPayload]
//     (tests/Functional/Fixture/UserController.php)
//   - UserDto — firstName/lastName (NotBlank), age (int, GreaterThan(18))
//     (tests/Functional/Fixture/UserDto.php)
//
// Each field is validated as the user leaves it: a precognitive POST
// (Precognition: true) runs server-side validation but the controller never
// executes. On submit, one final precognitive check covers the whole payload,
// then the real POST runs only if that check passed.

const ENDPOINT = 'http://localhost:8000/user';
const DEBOUNCE_MS = 200;

const form = document.getElementById('user');
const statusLine = document.getElementById('status');

// --- Payload ----------------------------------------------------------------

// #[MapRequestPayload] deserializes this JSON into UserDto, whose `age` is
// typed int — a string would be rejected as a type error, so coerce it.
function payload() {
  const fields = Object.fromEntries(new FormData(form));
  return JSON.stringify({ ...fields, age: Number(fields.age) });
}

// --- Error rendering ---------------------------------------------------------

// Error slots are keyed by the DTO property path (data-error-for="age" etc.).
function setError(field, message) {
  const slot = form.querySelector(`[data-error-for="${field}"]`);
  if (slot) slot.textContent = message ?? '';
}

function clearAllErrors() {
  for (const slot of form.querySelectorAll('.error')) slot.textContent = '';
}

// --- Precognitive requests ---------------------------------------------------

// One AbortController per validation scope ('*' = whole payload), so a newer
// check cancels the in-flight one it supersedes.
const inFlight = new Map();

// Ask the server to validate without executing the controller. `only` limits
// the reported violations to one field via Precognition-Validate-Only.
// Returns the violations — [] means the server answered 204 (valid).
async function requestValidation(only) {
  const scope = only ?? '*';
  inFlight.get(scope)?.abort();
  const controller = new AbortController();
  inFlight.set(scope, controller);

  const headers = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    Precognition: 'true',
  };
  if (only) headers['Precognition-Validate-Only'] = only;

  try {
    const response = await fetch(ENDPOINT, {
      method: 'POST',
      headers,
      body: payload(),
      signal: controller.signal,
    });
    if (response.status === 204) return [];
    const body = await response.json().catch(() => null);
    return body?.violations ?? [];
  } finally {
    if (inFlight.get(scope) === controller) inFlight.delete(scope);
  }
}

// --- Per-field validation on blur, debounced ----------------------------------

const debounceTimers = new Map();

function scheduleFieldValidation(field) {
  clearTimeout(debounceTimers.get(field));
  debounceTimers.set(field, setTimeout(() => validateField(field), DEBOUNCE_MS));
}

async function validateField(field) {
  try {
    const violations = await requestValidation(field);
    setError(field, violations.find((v) => v.propertyPath === field)?.title);
  } catch (error) {
    if (error.name !== 'AbortError') throw error; // aborted = superseded, ignore
  }
}

form.addEventListener('focusout', (event) => {
  if (event.target.name) scheduleFieldValidation(event.target.name);
});

// --- Submit -------------------------------------------------------------------

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  statusLine.textContent = '';
  clearAllErrors();

  let violations;
  try {
    violations = await requestValidation();
  } catch (error) {
    if (error.name === 'AbortError') return; // superseded by a newer submit
    throw error;
  }

  if (violations.length > 0) {
    for (const violation of violations) setError(violation.propertyPath, violation.title);
    return;
  }

  // Valid: real POST — no Precognition header, so UserController::create() runs.
  const response = await fetch(ENDPOINT, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: payload(),
  });
  statusLine.textContent = response.ok ? 'User created.' : 'Submission failed.';
});
