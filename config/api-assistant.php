<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

return [
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

    'api_route_name_prefix' => 'api.',

    'allow_where' => [
        'and',
        'or',
        'in',
        'notIn',
    ],

    'paginate' => 15,

    'order_by' => 'orderBy',
];
