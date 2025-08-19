<?php

namespace Lifo\AutocompleteBundle\Form\DataTransformer;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class Select2ValueTransformer implements DataTransformerInterface
{
    protected ?EntityManagerInterface $em;
    protected PropertyAccessor        $accessor;
    protected ?string                 $class;
    protected string                  $property;
    protected ?array                  $choices;
    protected ?array                  $tags;
    /** @var string|callable|null */
    protected $textProperty;

    public function __construct(?EntityManagerInterface $em,
                                ?string                 $class,
                                string                  $property,
                                string|callable|null    $textProperty = null,
                                ?array                  $tags = null,
                                ?array                  $choices = null)
    {
        $this->em = $em;
        $this->class = $class;
        $this->textProperty = $textProperty;
        $this->property = $property;
        $this->tags = $tags;
        $this->choices = $choices;
        $this->accessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * Transform the normalized $value (the Entity or Model from the App) to an array for use in the form template.
     */
    public function transform(mixed $value): mixed
    {
        if ($value === '' || $value === null) {
            return null;
        }

        // return the value as-is if its from a plain 'choices' list and not an Entity
        if ($this->choices && is_array($value) && in_array($value, $this->choices, true)) {
            return $value;
        }

        try {
            if (is_scalar($value)) {
                $id = $value;
            } else {
                $id = $this->accessor->getValue($value, $this->property);
            }

            if (is_callable($this->textProperty)) {
                $text = ($this->textProperty)($value);
            } elseif (is_scalar($value)) {
                $text = $value;
            } elseif ($this->textProperty !== null) {
                $text = $this->accessor->getValue($value, $this->textProperty);
            } elseif (is_object($value) && method_exists($value, '__toString')) {
                $text = (string)$value;
            } else {
                throw new Exception('Unable to determine Text field for entity. Did you set the "text_property" field correctly?');
            }
        } catch (Exception $e) {
            throw new TransformationFailedException($e->getMessage(), $e->getCode(), $e);
        }

        return (object)['id' => $id, 'text' => $text, 'data' => $value];
    }

    /**
     * Transform the scalar ID value back into a normalized Entity or Model for App ingestion.
     */
    public function reverseTransform(mixed $value): mixed
    {
        if ($value === '' || $value === null) {
            return null;
        }

        if ($this->class && $this->em) {
            if ($this->tags) {
                $len = strlen($this->tags['tag_id_prefix']);
                if (substr($value, 0, $len) === $this->tags['tag_id_prefix']) {
                    $value = substr($value, $len);
                    $ent = new $this->class;
                    $prop = $this->textProperty ?? 'text';
                    if ($this->accessor->isWritable($ent, $prop)) {
                        $this->accessor->setValue($ent, $prop, $value);
//                    } else {
//                        // todo throw error?
                    }
                    return $ent;
                }
            }

            $repo = $this->em->getRepository($this->class);
            if (!empty($this->property)) {
                return $repo->findOneBy(array($this->property => $value));
            } else {
                return $repo->find($value);
            }
        } elseif ($this->choices) {
            $found = array_filter($this->choices, function (array $c) use ($value) {
                return $c == $value || @$c['id'] == $value;
            });
            return $found ? reset($found) : null;
        }

        return $value;
    }
}
