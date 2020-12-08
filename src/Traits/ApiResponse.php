<?php

namespace  Orzlee\ApiAssistant\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\Response as FoundationResponse;

trait ApiResponse
{
    protected $statusCode = FoundationResponse::HTTP_OK;


    /**
     * @param array $data
     * @return JsonResponse
     */
    protected function respond($data = []): JsonResponse
    {
        return Response::json($data, $this->getStatusCode());
    }

    /**
     * @param $status
     * @param $data
     * @param null $code
     * @return JsonResponse|AnonymousResourceCollection
     */
    protected function status($status, $data, $code = null)
    {
        if ($code) {
            $this->setStatusCode($code);
        }

        $status = [
            'status' => $status,
            'code'   => $this->statusCode,
        ];

        return $data instanceof AnonymousResourceCollection
            ? $data->additional($status)
            : $this->respond(array_merge($data, $status));
    }

    /**
     * @param $message
     * @param int $code
     * @param string $status
     * @return JsonResponse
     */
    protected function failed($message, $code = FoundationResponse::HTTP_BAD_REQUEST, $status = 'error'): JsonResponse
    {
        return $this->setStatusCode($code)->message($message, $status);
    }

    /**
     * @param $message
     * @param string $status
     * @return JsonResponse
     */
    protected function message($message, $status = 'success'): JsonResponse
    {
        return $this->status($status, compact('message'));
    }

    /**
     * @param string $message
     * @return JsonResponse
     */
    protected function internalError($message = 'Internal Error!'): JsonResponse
    {
        return $this->failed($message, FoundationResponse::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * @param string $message
     * @return JsonResponse
     */
    protected function created($message = 'created'): JsonResponse
    {
        return $this->setStatusCode(FoundationResponse::HTTP_CREATED)->message($message);
    }

    /**
     * @param $data
     * @param string $status
     * @return JsonResponse|AnonymousResourceCollection
     */
    protected function success($data, $status = 'success')
    {
        return $this->status($status, $data instanceof AnonymousResourceCollection ? $data : compact('data'));
    }

    /**
     * @param string $message
     * @return JsonResponse
     */
    protected function notFound($message = 'Not found!'): JsonResponse
    {
        return $this->failed($message, FoundationResponse::HTTP_NOT_FOUND);
    }

    /**
     * @return int
     */
    protected function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @param int $statusCode
     * @return $this
     */
    protected function setStatusCode(int $statusCode)
    {
        $this->statusCode = $statusCode;

        return $this;
    }
}
