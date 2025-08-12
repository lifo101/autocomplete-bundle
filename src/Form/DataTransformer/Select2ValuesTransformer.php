<?php

namespace Lifo\AutocompleteBundle\Form\DataTransformer;

use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

class Select2ValuesTransformer extends Select2ValueTransformer
{
    /**
     * @return mixed[]
     */
    public function transform($array): array
    {
        if (!$array) {
            return [];
        }

        if ($array instanceof Collection) {
            $array = $array->toArray();
        }

        if (!is_array($array)) {
            throw new UnexpectedTypeException($array, 'array');
        }

        $return = [];
        foreach ($array as $entity) {
            $value = parent::transform($entity);
            if ($value !== null) {
                $return[] = $value;
            }
        }
        return $return;
    }


    /**
     * @return mixed[]
     */
    public function reverseTransform($array): array
    {
        if (!$array) {
            return [];
        }

        if (!is_array($array)) {
            throw new UnexpectedTypeException($array, 'array');
        }

        $res = [];
        foreach ($array as $a) {
            $res[] = parent::reverseTransform($a);
        }
        return $res;

//        if ($this->tags) {
//            $res = [];
//            foreach ($array as $a) {
//                $res[] = parent::reverseTransform($a);
//            }
//            return $res;
//        }
//
//        if ($this->class && $this->em) {
//            try {
//                return $this->em->createQueryBuilder()
//                    ->select('e')
//                    ->from($this->class, 'e')
//                    ->where('e.' . ($this->property ?? 'id') . ' IN (:ids)')
//                    ->setParameter('ids', $array)
//                    ->getQuery()
//                    ->getResult();
//            } catch (Exception $e) {
//                throw new TransformationFailedException('One or more property "' . ($this->property ?? '???') . '" values are invalid');
//            }
//        }
//        return $array;
    }
}
