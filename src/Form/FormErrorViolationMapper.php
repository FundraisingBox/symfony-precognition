<?php

/**
 * @author Clemens Krack <info@clemenskrack.com>
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FundraisingBox\Precognition\Form;

use RecursiveIteratorIterator;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;

use function array_reverse;
use function implode;

final class FormErrorViolationMapper
{
    public function map(FormInterface $form): ConstraintViolationListInterface
    {
        $violations = new ConstraintViolationList();

        foreach (new RecursiveIteratorIterator($form->getErrors(deep: true)) as $error) {
            if (!$error instanceof FormError) {
                continue;
            }

            $violations->add($this->mapError($error, $form));
        }

        return $violations;
    }

    private function mapError(FormError $error, FormInterface $rootForm): ConstraintViolationInterface
    {
        $cause = $error->getCause();
        $propertyPath = $this->propertyPath($error->getOrigin() ?? $rootForm);

        if ($cause instanceof ConstraintViolationInterface) {
            return new ConstraintViolation(
                $cause->getMessage(),
                $cause->getMessageTemplate(),
                $cause->getParameters(),
                $rootForm->getData(),
                $propertyPath,
                $cause->getInvalidValue(),
                $cause->getPlural(),
                $cause->getCode(),
                null,
                $cause->getCause()
            );
        }

        return new ConstraintViolation(
            $error->getMessage(),
            $error->getMessageTemplate(),
            $error->getMessageParameters(),
            $rootForm->getData(),
            $propertyPath,
            null,
            $error->getMessagePluralization()
        );
    }

    private function propertyPath(FormInterface $form): string
    {
        $segments = [];
        $current = $form;

        while (null !== $current->getParent()) {
            $segments[] = $current->getName();
            $current = $current->getParent();
        }

        return implode('.', array_reverse($segments));
    }
}
