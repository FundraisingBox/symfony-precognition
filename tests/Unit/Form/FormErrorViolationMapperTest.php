<?php

declare(strict_types=1);

namespace FundraisingBox\Precognition\Tests\Unit\Form;

use FundraisingBox\Precognition\Form\FormErrorViolationMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\ConstraintViolation;

#[CoversClass(FormErrorViolationMapper::class)]
final class FormErrorViolationMapperTest extends TestCase
{
    public function testReusesConstraintViolationDetailsWithRootlessFieldPath(): void
    {
        $form = Forms::createFormFactory()
            ->createNamedBuilder('task')
            ->add('title', TextType::class)
            ->getForm();
        $cause = new ConstraintViolation(
            'This value should not be blank.',
            'This value should not be blank.',
            ['{{ value }}' => '""'],
            null,
            'children[title].data',
            '',
            null,
            'c1051bb4-d103-4f74-8988-acbcafc7fdc3'
        );

        $form->get('title')->addError(new FormError('Rendered message', null, [], null, $cause));

        $violation = (new FormErrorViolationMapper())->map($form)->get(0);

        $this->assertSame('This value should not be blank.', $violation->getMessage());
        $this->assertSame('This value should not be blank.', $violation->getMessageTemplate());
        $this->assertSame(['{{ value }}' => '""'], $violation->getParameters());
        $this->assertSame('title', $violation->getPropertyPath());
        $this->assertSame('', $violation->getInvalidValue());
        $this->assertSame('c1051bb4-d103-4f74-8988-acbcafc7fdc3', $violation->getCode());
    }

    public function testMapsRootLevelFormErrorToEmptyPath(): void
    {
        $form = Forms::createFormFactory()->createNamedBuilder('task')->getForm();

        $form->addError(new FormError('Root error'));

        $violation = (new FormErrorViolationMapper())->map($form)->get(0);

        $this->assertSame('Root error', $violation->getMessage());
        $this->assertSame('', $violation->getPropertyPath());
    }

    public function testMapsNestedFieldPathWithoutRootFormName(): void
    {
        $factory = Forms::createFormFactory();
        $category = $factory->createNamedBuilder('category', FormType::class)
            ->add('name', TextType::class);
        $form = $factory->createNamedBuilder('task')
            ->add($category)
            ->getForm();

        $form->get('category')->get('name')->addError(new FormError('Name error'));

        $violation = (new FormErrorViolationMapper())->map($form)->get(0);

        $this->assertSame('category.name', $violation->getPropertyPath());
    }

    public function testMapsExtraFieldsErrorToRootPath(): void
    {
        $form = Forms::createFormFactory()
            ->createNamedBuilder('task')
            ->add('title', TextType::class)
            ->getForm();

        $form->addError(new FormError('This form should not contain extra fields.'));

        $violation = (new FormErrorViolationMapper())->map($form)->get(0);

        $this->assertSame('This form should not contain extra fields.', $violation->getMessage());
        $this->assertSame('', $violation->getPropertyPath());
    }
}
