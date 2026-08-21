# Validating Symfony Forms

Install the optional Form component:

```bash
composer require symfony/form
```

Classic Symfony Forms validate in the controller body when
`$form->handleRequest($request)` submits the form. A global precognitive
short-circuit would otherwise skip that code and return a false `204`, so form
support is opt-in per endpoint with `#[PrecognitiveForm]`:

```php
use FundraisingBox\Precognition\Attribute\PrecognitiveForm;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/task/new', methods: ['POST'])]
#[PrecognitiveForm(TaskType::class)]
public function new(Request $request): Response
{
    $form = $this->createForm(TaskType::class, new Task());
    $form->handleRequest($request);

    // Never runs for a precognitive request.
}
```

For a precognitive request, the bundle creates the annotated form type,
disables CSRF on that validation-only instance, submits the request payload,
and converts form errors into Symfony constraint violations. Normal,
non-precognitive submits still run the controller and retain the form's real
CSRF behavior.

> [!IMPORTANT]
> Disabling CSRF is limited to this throwaway form instance. The bundle does
> not invoke the controller or persist the submitted values, and normal
> authentication and authorization still apply. However, the form's event
> listeners, subscribers, data transformers, and callbacks process untrusted
> input without CSRF protection. Ensure they do not cause side effects.

`#[PrecognitiveForm]` implies precognitive opt-in. Do not add an extra
`#[Precognitive]` attribute to the same route.

## Field paths

Form violation paths omit the root form prefix. For a form named `task`, an
invalid `task[task]` input is reported as `task`, and
`Precognition-Validate-Only: task` matches it. See
[validating selected fields](validate-only.md) for path matching details.
