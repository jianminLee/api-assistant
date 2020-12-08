<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

return [
    /**
     * 已知异常捕获返回json错误消息
     * 键是已知异常需要捕获的异常，值是一个数组，自定义返回的消息和错误状态码
     * 已知异常捕获不会记录在laravel日志中
     */
    'catch_exceptions' => [
        AuthenticationException::class   => ['' => Response::HTTP_UNAUTHORIZED],
        ModelNotFoundException::class    => ['Model not found.' => Response::HTTP_NOT_FOUND],
        UnauthorizedHttpException::class => ['' => Response::HTTP_NOT_ACCEPTABLE],
        //        UnauthorizedException::class     => ['Unauthorized' => Response::HTTP_UNAUTHORIZED],
        QueryException::class            => [
            'Internal Server Error: Query Error.' => Response::HTTP_INTERNAL_SERVER_ERROR
        ],
        ValidationException::class       => ['' => Response::HTTP_UNPROCESSABLE_ENTITY],
    ],

    /**
     * 默认异常返回消息
     * 当catch_exceptions中没有被捕获的异常抛出，最终会返回默认异常json响应
     * 该异常会记录laravel日志
     */
    'default_exception_message' => 'System error, please try again later.',

    /**
     * api路由前缀
     * 通过api路由前缀、ajax请求、json请求判断是否应该捕获该异常并返回json响应
     */
    'api_route_name_prefix' => 'api.',

    /**
     * 条件过滤
     * 允许使用的条件过滤
     */
    'allow_operator' => [
        'and',
        'or',
        'in',
        'notIn',
    ],
    /**
     * 条件过滤对应的查询
     */
    'conditions' => [
        'where',
        'orWhere',
        'whereIn',
        'whereNotIn',
    ],

    /**
     * 默认分页大小
     */
    'paginate' => 15,

    /**
     * 排序字段名称
     */
    'order_by' => 'orderBy',

    /**
     * 闭包查询字段名称
     */
    'closure_query' => 'closures',

    /**
     * 模型缓存TAG
     */
    'model_cache_tag' => 'model_cache',
];
