<?php

namespace  Orzlee\ApiAssistant\Traits\Model;

use Illuminate\Database\Eloquent\Builder;

trait AutoLoadRelation
{

    public function scopeAutoLoadRelation(Builder $query, array $field = ['*'])
    {
        if (isset(self::$RELATION_FIELDS) && count(self::$RELATION_FIELDS)) {
            $query->with(
                array_keys(
                    $field === ['*']
                        ? array_diff(self::$RELATION_FIELDS, $field)
                        : array_intersect(self::$RELATION_FIELDS, $field)
                )
            );
        }
        return $query;
    }
}
