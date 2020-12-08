<?php

namespace  Orzlee\ApiAssistant\Traits\Model;

use Illuminate\Database\Eloquent\Builder;

trait AutoLoadRelation
{

    public function scopeAutoLoadRelation(Builder $query, array $field = ['*'])
    {
        if (method_exists(self::class, 'relationFields')
            && count($relationFields = self::relationFields())) {
            $query->with(
                array_keys(
                    $field === ['*']
                        ? array_diff($relationFields, $field)
                        : array_intersect($relationFields, $field)
                )
            );
        }
        return $query;
    }
}
