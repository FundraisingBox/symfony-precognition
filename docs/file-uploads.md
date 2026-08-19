# Validating uploaded files

Uploaded files can be validated precognitively through `#[MapUploadedFile]`:

```php
use FundraisingBox\Precognition\Attribute\Precognitive;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/user/picture', methods: ['PUT'])]
#[Precognitive]
public function changePicture(
    #[MapUploadedFile([
        new Assert\File(mimeTypes: ['image/png', 'image/jpeg']),
    ])]
    UploadedFile $picture,
): Response {
    // Never runs for a precognitive request.
}
```

If the endpoint should be usable precognitively without re-uploading the file,
make the argument nullable, for example `?UploadedFile $picture = null`.

A missing non-nullable file is rejected by Symfony with `422` before it creates
a validation violation list. In that case,
[`Precognition-Validate-Only`](validate-only.md) has nothing to filter.
