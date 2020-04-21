<?php


namespace SuperTokens\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use SuperTokens\Exceptions\SuperTokensException;
use SuperTokens\Exceptions\SuperTokensGeneralException;
use SuperTokens\Exceptions\SuperTokensTokenTheftException;
use SuperTokens\Exceptions\SuperTokensTryRefreshTokenException;
use SuperTokens\Exceptions\SuperTokensUnauthorisedException;
use SuperTokens\Helpers\Constants;
use SuperTokens\Helpers\CookieAndHeader;
use SuperTokens\Helpers\HandshakeInfo;
use SuperTokens\Helpers\Querier;

class ErrorMiddleware
{
    /**
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (!empty($response->exception)) {
            if (
                $response->exception instanceof SuperTokensTryRefreshTokenException
                ||
                $response->exception instanceof SuperTokensUnauthorisedException
                ||
                $response->exception instanceof SuperTokensTokenTheftException
            ) {
                $message = "Unauthorised";
                if ($response->exception instanceof SuperTokensTryRefreshTokenException) {
                    $message = "Try Refresh Token";
                }
                try {
                    $handshakeInfo = HandshakeInfo::getInstance();
                    CookieAndHeader::clearSessionFromCookie($response, $handshakeInfo->cookieDomain, $handshakeInfo->cookieSecure, $handshakeInfo->accessTokenPath, $handshakeInfo->refreshTokenPath, $handshakeInfo->sameSite);
                    $response->setStatusCode($handshakeInfo->getSessionExpiredStatusCode())->setContent($message);
                } catch (SuperTokensGeneralException | SuperTokensException $e) {
                    $response->setStatusCode(500)->setContent($e->getMessage());
                }
            }
        }
        return $response;
    }
}
