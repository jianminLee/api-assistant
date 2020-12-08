<?php

namespace  Orzlee\ApiAssistant\Traits\Controller;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait QueryRequestFilter
 * @package App\Api\Traits\Controller
 * @property \Illuminate\Database\Eloquent\Builder $query
 */
trait QueryRequestFilter
{
    protected static $allowOperator;

    protected static $paginate;

    protected $filterFields;
    protected $query;
    protected $request;
    protected static $conditions;
    protected static $orderBy;
    protected static $closureQuery;

    protected function bootQueryRequestFilter()
    {
        $config = app('config');

        self::$allowOperator = $config->get('api-assistant.allow_operator', ['and', 'or', 'in', 'notIn',]);
        self::$paginate       = $config->get('api-assistant.paginate', 15);
        self::$orderBy       = $config->get('api-assistant.order_by', 'orderBy');
        self::$conditions     = $config->get('api-assistant.conditions', ['where', 'orWhere', 'whereIn', 'whereNotIn']);
        self::$closureQuery  = $config->get('api-assistant.closure_query', 'closure');
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
            ? $fields
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
    ): Builder {
        if (empty($conditions)) {
            return $model;
        }
        $this->queryOrderByFilter(
            $fields,
            Arr::get($conditions, self::$orderBy, 'created_at'),
            $model,
            $delimiter
        );

        $fields = Arr::only($conditions, $this->queryFieldsFilter($fields, array_keys($conditions)));
        foreach ($fields as $field => $condition) {
            $wheres          = [];
            $operators       = [];
            $values          = [];
            $modelOperators = $model->getQuery()->operators;
            $valueIndex     = 0;
            if (substr_count($condition, $delimiter)) {
                $condition = explode(',', $condition);
                foreach ($condition as $value) {
                    if (in_array($value, $modelOperators)) {
                        $operators[] = $value;
                    } elseif (in_array($value, self::$allowOperator)) {
                        $wheres[] = $this->conditionConversion($value);
                    } else {
                        if (!Arr::has($operators, $valueIndex)) {
                            $operators[] = '=';
                        } elseif (last($operators) == 'like') {
                            $value = preg_replace('/~/', '%', $value);
                        }

                        if (!Arr::has($wheres, $valueIndex)) {
                            $wheres[] = 'where';
                        } elseif (in_array(last($wheres), ['whereNotIn', 'whereIn'])) {
                            $value = explode('~', $value);
                        }
                        $values[$valueIndex] = $value;
                        $valueIndex++;
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
     * @param string $orderBy
     * @param Builder $model
     * @param string $delimiter
     */
    protected function queryOrderByFilter(array $fields, string $orderBy, Builder $model, string $delimiter = ',')
    {
        if (empty($orderBy)) {
            $orderBy = 'created_at';
        }
        if (strpos($orderBy, $delimiter)) {
            list($field, $sort) = explode(',', $orderBy);
        } else {
            $field = $orderBy;
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
        $pageSize = (int)$this->request->get('page_size');

        return $pageSize ?: self::$paginate;
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
    ) {
        $firstWhere = head($wheres);
        if (count($wheres) > 1 && in_array($firstWhere, ['where', 'orWhere'])) {
            $model->{$firstWhere}(function ($query) use ($wheres, $field, $values, $operators) {
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
    ) {
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
        $firstWhere = head(explode(',', head(head($closures))));
        $firstWhere = in_array($firstWhere, ['and', 'or']) ? $firstWhere : 'and';
        foreach ($closures as $closure) {
            $model->{$this->conditionConversion($firstWhere)}(function ($query) use ($closure, $fields) {
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

        return preg_replace(array_map(function ($allowWhere) {
            return $allowWhere = "/{$allowWhere}/";
        }, self::$allowOperator), self::$conditions, $where);
    }

    /**
     * @param $model
     * @param Request $request
     */
    protected function init($model, Request $request)
    {
        $this->bootQueryRequestFilter();
        $this->filterFields = $this->queryFieldsFilter($fields = $model::fields(), $request->get('fields'));
        $query               = $model::autoLoadRelation(explode(',', $request->get('fields', '*')));
        $this->query         = $this
            ->closureConditionQuery($fields, $request->query(self::$closureQuery, []), $query)
            ->queryConditionFilter($fields, $request->query(), $query);
        $this->request       = $request;
    }

    /**
     * @param bool $paginate
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    protected function get($paginate = true)
    {
        if ($paginate && $this->request->query('paginate') == 'no') {
            return $this->query->get($this->filterFields);
        }

        return $this->paginate();
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    protected function paginate()
    {
        return $this->query->simplePaginate($this->fetchPaginateSize(), $this->filterFields);
    }
}
