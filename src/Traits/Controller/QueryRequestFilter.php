<?php

namespace  Orzlee\ApiAssistant\Traits\Controller;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait QueryRequestFilter
 * @package App\Api\Traits\Controller
 */
trait QueryRequestFilter
{
    protected static $allow_operator;

    protected static $paginate;

    protected $filter_fields;
    protected $query;
    protected $request;
    protected static $conditions;
    protected static $order_by;
    protected static $closure_query;

    public static function bootQueryRequestFilter()
    {
        $config = app('config');

        self::$allow_operator = $config->get('api-assistant.allow_operator', ['and', 'or', 'in', 'notIn',]);
        self::$paginate       = $config->get('api-assistant.paginate', 15);
        self::$order_by       = $config->get('api-assistant.order_by', 'orderBy');
        self::$conditions     = $config->get('api-assistant.conditions', ['where', 'orWhere', 'whereIn', 'whereNotIn']);
        self::$closure_query  = $config->get('api-assistant.closure_query', 'closure');
    }

    /**
     * @param array $fields
     * @param $filter
     * @param string $delimiter
     * @return array
     */
    protected function queryFieldsFilter(array $fields, $filter, string $delimiter = ','): array
    {
        return empty($filter) || (is_string($filter) && strpos($filter, '*') !== false)
            ? ['*']
            : array_intersect($fields, (is_string($filter) ? explode($delimiter, $filter) : $filter));
    }

    /**
     * @param array $fields
     * @param array $conditions
     * @param Builder $model
     * @param string $delimiter
     * @return Builder
     */
    protected function queryConditionFilter(
        array $fields,
        array $conditions,
        Builder $model,
        string $delimiter = ','
    ): Builder
    {
        if (empty($conditions)) {
            return $model;
        }
        $this->queryOrderByFilter(
            $fields,
            Arr::get($conditions, self::$order_by, 'created_at'),
            $model,
            $delimiter
        );

        $fields = Arr::only($conditions, $this->queryFieldsFilter($fields, array_keys($conditions)));
        foreach ($fields as $field => $condition) {
            $wheres          = [];
            $operators       = [];
            $values          = [];
            $model_operators = $model->getQuery()->operators;
            $value_index     = 0;
            if (substr_count($condition, $delimiter)) {
                $condition = explode(',', $condition);
                foreach ($condition as $value) {
                    if (in_array($value, $model_operators)) {
                        $operators[] = $value;
                    } elseif (in_array($value, self::$allow_operator)) {
                        $wheres[] = $this->conditionConversion($value);
                    } else {
                        if (!Arr::has($operators, $value_index)) {
                            $operators[] = '=';
                        } elseif (last($operators) == 'like') {
                            $value = preg_replace('/~/', '%', $value);
                        }

                        if (!Arr::has($wheres, $value_index)) {
                            $wheres[] = 'where';
                        } elseif (in_array(last($wheres), ['whereNotIn', 'whereIn'])) {
                            $value = explode('~', $value);
                        }
                        $values[$value_index] = $value;
                        $value_index++;
                    }
                }
            } else {
                $operators = [$condition];
                $wheres[]  = 'where';
            }
            $this->setConditions($model, $field, $wheres, $values ?? [], $operators);
        }

        return $model;
    }

    /**
     * @param array $fields
     * @param string $order_by
     * @param Builder $model
     * @param string $delimiter
     */
    protected function queryOrderByFilter(array $fields, string $order_by, Builder $model, string $delimiter = ',')
    {
        if (empty($order_by)) {
            $order_by = 'created_at';
        }
        if (strpos($order_by, $delimiter)) {
            list($field, $sort) = explode(',', $order_by);
        } else {
            $field = $order_by;
        }
        if ($field = head($this->queryFieldsFilter($fields, $field))) {
            $model->orderBy($field, $sort ?? 'desc');
        }
    }

    /**
     * @return int
     */
    protected function fetchPaginateSize(): int
    {
        $page_size = (int)$this->request->get('page_size');

        return $page_size ? $page_size : self::$paginate;
    }

    /**
     * @param Builder $model
     * @param string $field
     * @param array $wheres
     * @param array $values
     * @param array $operators
     */
    protected function setConditions(
        Builder $model,
        string $field,
        array $wheres,
        array $values = [],
        array $operators = []
    )
    {
        $first_where = head($wheres);
        if (count($wheres) > 1 && in_array($first_where, ['where', 'orWhere'])) {
            $model->{$first_where}(function ($query) use ($wheres, $field, $values, $operators) {
                foreach ($wheres as $key => $where) {
                    $this->setCondition($query, $field, Arr::get($values, $key), Arr::get($operators, $key), $where);
                }
            });

            return;
        }
        $this->setCondition($model, $field, head($values), head($operators), head($wheres));
    }

    /**
     * @param Builder $model
     * @param string $field
     * @param null $value
     * @param string $operator
     * @param string $where
     * @return $this
     */
    protected function setCondition(
        Builder $model,
        string $field,
        $value = null,
        $operator = '=',
        string $where = 'where'
    )
    {
        in_array($where, ['where', 'orWhere'])
            ? $model->{$where}($field, $operator, $value ?? null)
            : $model->{$where}($field, $value ?? null);

        return $this;
    }

    /**
     * @param array $fields
     * @param array $closures
     * @param Builder $model
     * @return $this
     */
    protected function closureConditionQuery(array $fields, array $closures, Builder $model)
    {
        if (empty($fields) || empty($closures)) {
            return $this;
        }
        $first_where = head(explode(',', head(head($closures))));
        $first_where = in_array($first_where, ['and', 'or']) ? $first_where : 'and';
        foreach ($closures as $closure) {
            $model->{$this->conditionConversion($first_where)}(function ($query) use ($closure, $fields) {
                $this->queryConditionFilter($fields, $closure, $query);
            });
        }

        return $this;
    }

    /**
     * @param string $where
     * @return string
     */
    protected function conditionConversion(string $where): string
    {
        if (empty($where)) {
            return 'where';
        }

        return preg_replace(array_map(function ($allow_where) {
            return $allow_where = "/{$allow_where}/";
        }, self::$allow_operator), self::$conditions, $where);
    }

    /**
     * @param $model
     * @param Request $request
     */
    protected function init($model, Request $request)
    {
        $this->filter_fields = $this->queryFieldsFilter($model::FIELDS, $request->get('fields'));
        $query               = $model::autoLoadRelation(explode(',', $request->get('fields', ['*'])));
        $this->query         = $this
            ->closureConditionQuery($model::FIELDS, $request->query(self::$closure_query, []), $query)
            ->queryConditionFilter($model::FIELDS, $request->query(), $query);
        $this->request       = $request;
    }

    /**
     * @param bool $paginate
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    protected function get($paginate = true)
    {
        if ($paginate && $this->request->query('paginate') == 'no') {
            return $this->query->get($this->filter_fields);
        }

        return $this->paginate();
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    protected function paginate()
    {
        return $this->query->paginate($this->fetchPaginateSize(), $this->filter_fields);
    }
}
