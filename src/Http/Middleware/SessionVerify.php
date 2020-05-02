<?php


namespace SuperTokens\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use SuperTokens\Exceptions\SuperTokensException;
use SuperTokens\Exceptions\SuperTokensGeneralException;
use SuperTokens\Exceptions\SuperTokensTokenTheftException;
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
     * @throws SuperTokensGeneralException
     */
    public function handle(Request $request, Closure $next, string $antiCsrfCheck = null)
    {
//        try {
        if ($request->path() === HandshakeInfo::getInstance()->refreshTokenPath && $request->isMethod("post")) {
            $response = $next($request);
        } else {
            try {
                if (!isset($antiCsrfCheck)) {
                    $antiCsrfCheckBoolean = !($request->isMethod('get'));
                } else {
                    $antiCsrfCheckBoolean = strtolower($antiCsrfCheck) === "true";
                }
                $session = SuperTokens::getSession($request, null, $antiCsrfCheckBoolean);
            } catch (SuperTokensTryRefreshTokenException | SuperTokensUnauthorisedException $e) {
                $response = new Response();
                $message = "Try Refresh Token";
                $handshakeInfo = HandshakeInfo::getInstance();
                if ($response->exception instanceof SuperTokensUnauthorisedException) {
                    $message = "Unauthorised";
                    CookieAndHeader::clearSessionFromCookie($response, $handshakeInfo->cookieDomain, $handshakeInfo->cookieSecure, $handshakeInfo->accessTokenPath, $handshakeInfo->refreshTokenPath, $handshakeInfo->sameSite);
                }
                $response->setStatusCode($handshakeInfo->getSessionExpiredStatusCode())->setContent($message);
                return $response;
            }

            $request->merge(['supertokenSession' => $session]);
            $response = $next($request);

            if (count($session->newAccessTokenInfo) !== 0 && !$session->removeCookies) {
                $accessToken = $session->newAccessTokenInfo;
                CookieAndHeader::attachAccessTokenToCookie($response, $accessToken['token'], $accessToken['expiry'], $accessToken['domain'], $accessToken['cookieSecure'], $accessToken['cookiePath'], $accessToken['sameSite']);
            }

            if ($session->removeCookies) {
                $handshakeInfo = HandshakeInfo::getInstance();
                CookieAndHeader::clearSessionFromCookie($response, $handshakeInfo->cookieDomain, $handshakeInfo->cookieSecure, $handshakeInfo->accessTokenPath, $handshakeInfo->refreshTokenPath, $handshakeInfo->sameSite);
            }
        }

        // TODO: I don't undertsand this whole if else if thing.. Please explain what you are doing here.
        if (!empty($response->exception)) {
            if (
                $response->exception instanceof SuperTokensTryRefreshTokenException
                ||
                $response->exception instanceof SuperTokensUnauthorisedException
            ) {
                $message = "Try Refresh Token";
                $handshakeInfo = HandshakeInfo::getInstance();
                if ($response->exception instanceof SuperTokensUnauthorisedException) {
                    $message = "Unauthorised";
                    CookieAndHeader::clearSessionFromCookie($response, $handshakeInfo->cookieDomain, $handshakeInfo->cookieSecure, $handshakeInfo->accessTokenPath, $handshakeInfo->refreshTokenPath, $handshakeInfo->sameSite);
                }
                $response->setStatusCode($handshakeInfo->getSessionExpiredStatusCode())->setContent($message);
            }
        }
        return $response;
//        } catch (SuperTokensGeneralException | SuperTokensException $e) {
//            // TODO: Why do you need this try - catch?
//            // Because get handshake info can throw these exceptions. Either we make this handler throw those error or catch them here
//            $response = new Response();
//            $response->setStatusCode(500)->setContent($e->getMessage());
//            return $response;
//        }
    }
}



//class SessionVerify
//{
//    /**
//     * @param Request $request
//     * @param Closure $next
//     * @param string $antiCsrfCheck
//     * @return mixed
//     * @throws SuperTokensGeneralException
//     * @throws SuperTokensTryRefreshTokenException
//     * @throws SuperTokensUnauthorisedException
//     * @throws SuperTokensTokenTheftException
//     */
//    public function handle(Request $request, Closure $next, string $antiCsrfCheck = null)
//    {
//        $session = null;
//        $response = null;
//        if ($request->path() === HandshakeInfo::getInstance()->refreshTokenPath &&
//            $request->isMethod("post")) {
//
//            $session = SuperTokens::refreshSession($request, null);
//            $response = $next($request);
//
//        } else {
//
//            if (!isset($antiCsrfCheck)) {
//                $antiCsrfCheckBoolean = !($request->isMethod('get'));   // TODO: and method is not trace and not options
//            } else {
//                $antiCsrfCheckBoolean = strtolower($antiCsrfCheck) === "true";
//            }
//            $session = SuperTokens::getSession($request, null, $antiCsrfCheckBoolean);
//            $request->merge(['supertokens' => $session]);
//            $response = $next($request);
//
//        }
//
//        if ($session->removeCookies) {
//            $handshakeInfo = HandshakeInfo::getInstance();
//            CookieAndHeader::clearSessionFromCookie($response, $handshakeInfo->cookieDomain, $handshakeInfo->cookieSecure, $handshakeInfo->accessTokenPath, $handshakeInfo->refreshTokenPath, $handshakeInfo->sameSite);
//        } else {
//            if (count($session->newAccessTokenInfo) !== 0) {
//                $accessToken = $session->newAccessTokenInfo;
//                CookieAndHeader::attachAccessTokenToCookie($response, $accessToken['token'], $accessToken['expiry'], $accessToken['domain'], $accessToken['cookieSecure'], $accessToken['cookiePath'], $accessToken['sameSite']);
//            }
//            if (count($session->newRefreshTokenInfo) !== 0) {
//                $refreshToken = $session->newRefreshTokenInfo;
//                CookieAndHeader::attachRefreshTokenToCookie($response, $refreshToken['token'], $refreshToken['expiry'], $refreshToken['domain'], $refreshToken['cookieSecure'], $refreshToken['cookiePath'], $refreshToken['sameSite']);
//            }
//            if (count($session->newIdRefreshTokenInfo) !== 0) {
//                $idRefreshToken = $session->newIdRefreshTokenInfo;
//                CookieAndHeader::attachIdRefreshTokenToCookieAndHeader($response, $idRefreshToken['token'], $idRefreshToken['expiry'], $idRefreshToken['domain'], $idRefreshToken['cookieSecure'], $idRefreshToken['cookiePath'], $idRefreshToken['sameSite']);
//            }
//            if (isset($session->newAntiCsrfToken)) {
//                CookieAndHeader::attachAntiCsrfHeader($response, $session->newAntiCsrfToken);
//            }
//        }
//
//        return $response;
//    }
//}
