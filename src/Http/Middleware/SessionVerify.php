<?php


namespace SuperTokens\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use SuperTokens\Exceptions\SuperTokensException;
use SuperTokens\Exceptions\SuperTokensGeneralException;
use SuperTokens\Exceptions\SuperTokensTryRefreshTokenException;
use SuperTokens\Exceptions\SuperTokensUnauthorisedException;
use SuperTokens\Helpers\Constants;
use SuperTokens\Helpers\CookieAndHeader;
use SuperTokens\Helpers\HandshakeInfo;
use SuperTokens\Helpers\Querier;
use SuperTokens\Session;
use SuperTokens\SuperTokens;

class SessionVerify
{
    /**
     * @param Request $request
     * @param Closure $next
     * @param string $antiCsrfCheck
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $antiCsrfCheck = "true")
    {
        try {
            try {
                $antiCsrfCheckBoolean = strtolower($antiCsrfCheck) === "true";
                $session = SuperTokens::getSessionForMiddleware($request, $antiCsrfCheckBoolean);
            } catch (SuperTokensTryRefreshTokenException | SuperTokensUnauthorisedException $e) {
                $response = new Response();
                $message = "Unauthorised";
                if ($response->exception instanceof SuperTokensTryRefreshTokenException) {
                    $message = "Try Refresh Token";
                }
                $handshakeInfo = HandshakeInfo::getInstance();
                CookieAndHeader::clearSessionFromCookie($response, $handshakeInfo->cookieDomain, $handshakeInfo->cookieSecure, $handshakeInfo->accessTokenPath, $handshakeInfo->refreshTokenPath, $handshakeInfo->sameSite);
                $response->setStatusCode($handshakeInfo->getSessionExpiredStatusCode())->setContent($message);
                return $response;
            } catch (SuperTokensGeneralException | SuperTokensException $e) {
                $response = new Response();
                $response->setStatusCode(500)->setContent($e->getMessage());
                return $response;
            }
            $session = new Session($session['session']['handle'], $session['session']['userId'], $session['session']['userDataInJWT']);

            $request->merge(['supertokenSession' => $session]);
            $response = $next($request);

            if (!empty($response->exception)) {
                if (
                    !($response->exception instanceof SuperTokensTryRefreshTokenException)
                    &&
                    !($response->exception instanceof SuperTokensUnauthorisedException)
                ) {
                    if (isset($session['accessToken'])) {
                        $accessToken = $session['accessToken'];
                        $accessTokenSameSite = Constants::SAME_SITE_COOKIE_DEFAULT_VALUE;
                        if (Querier::getInstance()->getApiVersion() !== "1.0") {
                            $accessTokenSameSite = $accessToken['sameSite'];
                        }
                        CookieAndHeader::attachAccessTokenToCookie($response, $accessToken['token'], $accessToken['expiry'], $accessToken['domain'], $accessToken['cookieSecure'], $accessToken['cookiePath'], $accessTokenSameSite);
                    }
                } elseif (
                    $response->exception instanceof SuperTokensTryRefreshTokenException
                    ||
                    $response->exception instanceof SuperTokensUnauthorisedException
                ) {
                    $message = "Unauthorised";
                    if ($response->exception instanceof SuperTokensTryRefreshTokenException) {
                        $message = "Try Refresh Token";
                    }
                    $handshakeInfo = HandshakeInfo::getInstance();
                    CookieAndHeader::clearSessionFromCookie($response, $handshakeInfo->cookieDomain, $handshakeInfo->cookieSecure, $handshakeInfo->accessTokenPath, $handshakeInfo->refreshTokenPath, $handshakeInfo->sameSite);
                    $response->setStatusCode($handshakeInfo->getSessionExpiredStatusCode())->setContent($message);
                }
            }
            return $response;
        } catch (SuperTokensGeneralException | SuperTokensException $e) {
            $response = new Response();
            $response->setStatusCode(500)->setContent($e->getMessage());
            return $response;
        }
    }
}
