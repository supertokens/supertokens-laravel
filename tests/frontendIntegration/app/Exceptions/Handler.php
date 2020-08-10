<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use \SuperTokens\SuperTokens;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Exception
     */
    public function report(\Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, \Throwable $exception)
    {
        try {
            return SuperTokens::handleError($request, $exception, [
                'onUnauthorised' => function ($exception, $request, $response) {
                    return $response->setStatusCode(440)->setContent("Please login again");
                },
                'onTryRefreshToken' => function ($exception, $request, $response) {
                    return $response->setStatusCode(440)->setContent("Call the refresh API");
                },
                'onTokenTheftDetected' => function ($sessionHandle, $userId, $request, $response) {
                    SuperTokens::revokeSession($sessionHandle);
                    return $response->setStatusCode(440)->setContent("You are being attacked");
                }
            ]);
        } catch (\Exception $err) {
            $exception = $err;
        }
        return parent::render($request, $exception);
    }
}
