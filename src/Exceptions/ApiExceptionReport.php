<?php


namespace Orzlee\ApiAssistant\Exceptions;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Orzlee\ApiAssistant\Traits\ApiResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Contracts\Debug\ExceptionHandler;

class ApiExceptionReport
{
    use ApiResponse;

    protected $exception;

    protected $request;

    protected $report;

    protected static $CATCH_EXCEPTION;

    protected $config;

    public function __construct(Request $request, \Throwable $exception)
    {
        $this->config = app('config');
        $this->request   = $request;
        $this->exception = $exception;
        self::$CATCH_EXCEPTION = $this->config->get('api-assistant.catch_exceptions');
    }

    /**
     * @return bool
     */
    public function shouldCatchException(): bool
    {
        $route = Route::getCurrentRoute();
        if ($route && strpos($route->getName(), $this->config->get('api-assistant.api_route_name_prefix')) !== false
            || $this->request->wantsJson()
            || $this->request->ajax()
        ) {
            foreach (array_keys(self::$CATCH_EXCEPTION) as $exception) {
                if ($this->exception instanceof $exception) {
                    $this->report = $exception;

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param \Throwable $exception
     * @return static
     */
    public static function make(\Throwable $exception)
    {
        return new static(app('request'), $exception);
    }

    /**
     * @return JsonResponse
     */
    public function report(): JsonResponse
    {
        list($message, $code) = Arr::flatten(Arr::divide(Arr::get(self::$CATCH_EXCEPTION, $this->report)));

        if ($this->exception instanceof ValidationException) {
            $message = $this->exception->errors();
        } elseif (empty($message)) {
            $message = $this->config->get('api-assistant.default_exception_message', $this->exception->getMessage());
            app(ExceptionHandler::class)::report($this->exception);
        } elseif ($path = $this->config->get('api-assistant.exception_trans_path')) {
            $message = __($path . '.' . $message);
        }

        return $this->failed($message, $code);
    }
}
