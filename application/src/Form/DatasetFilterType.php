<?php

namespace App\Form;

use App\Model\Dataset;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class DatasetFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $availableValuesGetter = function(string $getter) use ($options): array {
            return array_unique(
                array_reduce(
                    $options['datasets'],
                    function (array $carry, Dataset $dataset) use ($getter): array {
                        $carry[$dataset->$getter()] = $dataset->$getter();

                        return $carry;
                    },
                    [],
                )
            );
        };

        $builder
            ->add('size', ChoiceType::class, [
                'label' => 'Site size',
                'choices' => $availableValuesGetter('getSize'),
                'placeholder' => '(Any size site)',
                'required' => false,
            ])
            ->add('branch', ChoiceType::class, [
                'label' => 'Moodle Branch',
                'choices' => $availableValuesGetter('getBranch'),
                'placeholder' => '(Any branch)',
                'required' => false,
            ])
            ->add('rampup', ChoiceType::class, [
                'label' => 'Ramp-up Period',
                'choices' => $availableValuesGetter('getRampup'),
                'placeholder' => '(Any period)',
                'required' => false,
            ])
            ->add('throughput', ChoiceType::class, [
                'label' => 'Throughput',
                'choices' => $availableValuesGetter('getThroughput'),
                'placeholder' => '(Any throughput)',
                'required' => false,
            ])
            ->add('loops', ChoiceType::class, [
                'label' => 'Test Loops',
            'choices' => $availableValuesGetter('getLoopCount'),
                'placeholder' => '(Any number)',
                'required' => false,
            ])
            ->add('users', ChoiceType::class, [
                'label' => 'Users',
                'choices' => $availableValuesGetter('getUsers'),
                'placeholder' => '(Any number)',
                'required' => false,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Apply filter',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'datasets' => [],
        ]);

        $resolver->setAllowedTypes('datasets', 'iterable');
    }
}
