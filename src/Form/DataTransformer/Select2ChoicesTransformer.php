<?php

namespace Lifo\AutocompleteBundle\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

class Select2ChoicesTransformer implements DataTransformerInterface
{
    protected array $choices;

    public function __construct(array $choices)
    {
        $this->choices = $choices;
    }

    public function transform(mixed $value): mixed
    {
        // return the choice array element for the value given
        if ($this->choices && $value !== null) {
            $found = array_filter($this->choices, fn($c) => @$c['id'] == $value);
            return $found ? reset($found) : null;
        }
        return $value;
    }

    public function reverseTransform(mixed $value): mixed
    {
        // return the ID for the model
        if (is_array($value) && $this->choices) {
            return @$value['id'] ?? $value;
        }
        return $value;
    }

}
