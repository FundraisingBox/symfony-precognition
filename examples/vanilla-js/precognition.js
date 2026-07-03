// Dependency-free client for the Symfony Precognition bundle.
//
// It validates each field as the user leaves it, without submitting the form:
// a precognitive POST (Precognition: true) runs server-side validation but the
// controller never executes. On submit it runs one final precognitive check of
// the whole payload, then performs the real POST only if that check passes.

const ENDPOINT = 'http://localhost:8000/users';
const THROTTLE_MS = 200;

const form = document.getElementById('registration');
const statusLine = document.getElementById('status');
const inputs = [...form.querySelectorAll('[data-field]')];

// One AbortController and one debounce timer per field, so a newer keystroke
// cancels the previous in-flight validation for that same field.
const inFlight = new Map();
const timers = new Map();

// Build the request body from the form. Nested paths (address.street) are
// expanded into nested objects, matching the server DTO.
function payload() {
  const body = {};
  for (const input of inputs) {
    const segments = input.dataset.field.split('.');
    let target = body;
    while (segments.length > 1) {
      const key = segments.shift();
      target = target[key] ??= {};
    }
    target[segments[0]] = input.value;
  }
  return body;
}

function setError(field, message) {
  const slot = form.querySelector(`[data-error-for="${field}"]`);
  if (slot) slot.textContent = message ?? '';
}

function clearErrors(fields) {
  for (const field of fields) setError(field, null);
}

// Send a precognitive request. `only` limits which fields are reported via the
// Precognition-Validate-Only header; omit it to validate the whole payload.
async function precognite(only) {
  const key = only ? only.join(',') : '*';

  // Cancel any earlier validation covering the same field(s).
  inFlight.get(key)?.abort();
  const controller = new AbortController();
  inFlight.set(key, controller);

  const headers = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    Precognition: 'true',
  };
  if (only) headers['Precognition-Validate-Only'] = only.join(',');

  const response = await fetch(ENDPOINT, {
    method: 'POST',
    headers,
    body: JSON.stringify(payload()),
    signal: controller.signal,
  });

  inFlight.delete(key);
  return response;
}

// Read { violations: [{ propertyPath, title }] } from a 422 problem+json body.
async function violations(response) {
  const body = await response.json().catch(() => null);
  return body?.violations ?? [];
}

// Validate a single field on blur, debounced.
function scheduleFieldValidation(field) {
  clearTimeout(timers.get(field));
  timers.set(
    field,
    setTimeout(async () => {
      try {
        const response = await precognite([field]);
        if (response.status === 204) {
          setError(field, null);
          return;
        }
        const matching = (await violations(response)).filter((v) => v.propertyPath === field);
        setError(field, matching[0]?.title ?? null);
      } catch (error) {
        if (error.name !== 'AbortError') throw error; // ignore superseded requests
      }
    }, THROTTLE_MS),
  );
}

for (const input of inputs) {
  input.addEventListener('blur', () => scheduleFieldValidation(input.dataset.field));
}

// On submit: one full precognitive check, then the real POST if it passed.
form.addEventListener('submit', async (event) => {
  event.preventDefault();
  statusLine.textContent = '';
  clearErrors(inputs.map((i) => i.dataset.field));

  const check = await precognite();
  if (check.status !== 204) {
    for (const violation of await violations(check)) {
      setError(violation.propertyPath, violation.title);
    }
    return;
  }

  // Valid: perform the real submission (no Precognition header, so the
  // controller runs).
  const created = await fetch(ENDPOINT, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(payload()),
  });
  statusLine.textContent = created.ok ? 'Account created.' : 'Submission failed.';
});
