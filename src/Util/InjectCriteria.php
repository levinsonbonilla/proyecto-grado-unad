<?php


namespace App\Util;

use Doctrine\Common\Collections\Criteria;

class InjectCriteria
{
    public static function addCriteria(
        array $fieldNames = ["active" => true],
        string $tableAlias = ''
    ): Criteria {
        $criteria = Criteria::create();

        foreach ($fieldNames as $key => $field) {
            $criteria = $criteria->andWhere(Criteria::expr()->eq(
                ($tableAlias == '') ? $key : "$tableAlias.$key",
                $field
            ));
        }

        return $criteria;
    }
}
