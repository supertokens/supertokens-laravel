<?php


namespace SuperTokens\Tests;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Exception;
use Illuminate\Support\Facades\Config;
use SuperTokens\Exceptions\SuperTokensGeneralException;
use SuperTokens\Helpers\Constants;
use SuperTokens\Http\Middleware;
use SuperTokens\SuperTokens;

class MiddlewareTest extends TestCase
{
    /**
     * @throws SuperTokensGeneralException
     */
    protected function setUp(): void
    {
        parent::setUp();
        Utils::reset();
        Utils::cleanST();
        Utils::setupST();
    }

    /**
     * @throws SuperTokensGeneralException
     */
    protected function tearDown(): void
    {
        Utils::reset();
        Utils::cleanST();
        parent::tearDown();
    }

    public function testMiddleware(): void
    {
        Config::set('supertokens.hosts', 'https://try.supertokens.io');
        Config::set('supertokens.refreshTokenPath', Utils::TEST_REFRESH_TOKEN_PATH_KEY_VALUE);
        Config::set('supertokens.cookieDomain', Utils::TEST_COOKIE_DOMAIN_VALUE);
        Config::set('supertokens.cookieSecure', true);
        Config::set('supertokens.accessTokenPath', Utils::TEST_ACCESS_TOKEN_PATH_VALUE);
        $response1 = new Response();
        SuperTokens::createNewSession($response1, "testUserId", [], []);
        $responseData1 = Utils::extractInfoFromResponse($response1);

        $request2 = new Request([], [], [], [
            'sAccessToken' => $responseData1['accessToken'],
            'sIdRefreshToken' => $responseData1['idRefreshToken']
        ]);
        $request2->headers->set("anti-csrf", $responseData1['antiCsrf']);
        $response2 = (new Middleware)->handle($request2, function (Request $req) {
            $responseObject = new Response();
            $userId = $req->supertokens->getUserId();
            return $responseObject->setStatusCode(200)->setContent($userId);
        });

        $this->assertEquals("testUserId", $response2->getContent());

        $request3 = new Request([], [], [], [
            'sRefreshToken' => $responseData1['refreshToken']
        ]);
        $request3->setMethod("post");
        $request3->server->set("REQUEST_URI", Utils::TEST_REFRESH_TOKEN_PATH_KEY_VALUE_TEST_MIDDLEWARE);
        $response3 = (new Middleware)->handle($request3, function (Request $req) {
            $responseObject = new Response();
            return $responseObject->setStatusCode(200)->setContent("");
        });
        $responseData2 = Utils::extractInfoFromResponse($response3);

        $this->assertNotNull($responseData2["idRefreshTokenFromHeader"]);
        $this->assertNotNull($responseData2["accessToken"]);
        $this->assertNotNull($responseData2["refreshToken"]);
        $this->assertNotNull($responseData2["idRefreshToken"]);
        $this->assertNotNull($responseData2["antiCsrf"]);
        $this->assertNotEquals($responseData1['accessToken'], $responseData2['accessToken']);
        $this->assertNotEquals($responseData1['refreshToken'], $responseData2['refreshToken']);
        $this->assertNotEquals($responseData1['idRefreshToken'], $responseData2['idRefreshToken']);
        $this->assertNotEquals($responseData1['antiCsrf'], $responseData2['antiCsrf']);
        $this->assertTrue($responseData2["accessTokenCookie"]->getPath() === Utils::TEST_ACCESS_TOKEN_PATH_VALUE);
        $this->assertTrue($responseData2["accessTokenCookie"]->getDomain() === Utils::TEST_COOKIE_DOMAIN_VALUE);
        $this->assertTrue($responseData2["accessTokenCookie"]->getSameSite() === "none");
        $this->assertTrue($responseData2["accessTokenCookie"]->isHttpOnly());
        $this->assertTrue($responseData2["accessTokenCookie"]->isSecure());
        $this->assertTrue($responseData2["refreshTokenCookie"]->getPath() === Utils::TEST_REFRESH_TOKEN_PATH_KEY_VALUE);
        $this->assertTrue($responseData2["refreshTokenCookie"]->getDomain() === Utils::TEST_COOKIE_DOMAIN_VALUE);
        $this->assertTrue($responseData2["refreshTokenCookie"]->getSameSite() === "none");
        $this->assertTrue($responseData2["refreshTokenCookie"]->isHttpOnly());
        $this->assertTrue($responseData2["refreshTokenCookie"]->isSecure());
        $this->assertTrue($responseData2["idRefreshTokenCookie"]->getPath() === Utils::TEST_ACCESS_TOKEN_PATH_VALUE);
        $this->assertTrue($responseData2["idRefreshTokenCookie"]->getDomain() === Utils::TEST_COOKIE_DOMAIN_VALUE);
        $this->assertTrue($responseData2["idRefreshTokenCookie"]->getSameSite() === "none");
        $this->assertTrue($responseData2["idRefreshTokenCookie"]->isHttpOnly());
        $this->assertTrue($responseData2["idRefreshTokenCookie"]->isSecure());

        $request4 = new Request([], [], [], [
            'sAccessToken' => $responseData2['accessToken'],
            'sIdRefreshToken' => $responseData2['idRefreshToken']
        ]);
        $response4 = (new Middleware)->handle($request4, function (Request $req) {
            $responseObject = new Response();
            $userId = $req->supertokens->getUserId();
            return $responseObject->setStatusCode(200)->setContent($userId);
        }, "false");
        $responseData3 = Utils::extractInfoFromResponse($response4);
        $this->assertNotNull($responseData3["accessToken"]);
        $this->assertTrue($responseData3["accessTokenCookie"]->getPath() === Utils::TEST_ACCESS_TOKEN_PATH_VALUE);
        $this->assertTrue($responseData3["accessTokenCookie"]->getDomain() === Utils::TEST_COOKIE_DOMAIN_VALUE);
        $this->assertTrue($responseData3["accessTokenCookie"]->getSameSite() === "none");
        $this->assertTrue($responseData3["accessTokenCookie"]->isHttpOnly());
        $this->assertTrue($responseData3["accessTokenCookie"]->isSecure());
        $this->assertNull($responseData3["idRefreshTokenFromHeader"]);
        $this->assertNull($responseData3["refreshToken"]);
        $this->assertNull($responseData3["idRefreshToken"]);
        $this->assertNull($responseData3["antiCsrf"]);

        $request5 = new Request([], [], [], [
            'sAccessToken' => $responseData3['accessToken'],
            'sIdRefreshToken' => $responseData2['idRefreshToken']
        ]);
        $request5->headers->set("anti-csrf", $responseData2['antiCsrf']);
        $response5 = (new Middleware)->handle($request5, function (Request $req) {
            $responseObject = new Response();
            $userId = $req->supertokens->getUserId();
            return $responseObject->setStatusCode(200)->setContent($userId);
        });

        $this->assertEquals("testUserId", $response5->getContent());

        $request6 = new Request();
        try {
            (new Middleware)->handle(new Request, function () {
            });
            $this->assertTrue(false);
        } catch (Exception $e) {
            $result = SuperTokens::handleError($request6, $e, [
                'onUnauthorised' => function ($exception, $request, $response) {
                    return true;
                },
                'onTryRefreshToken' => function ($exception, $request, $response) {
                    return false;
                },
                'onTokenTheftDetected' => function ($sessionHandle, $userId, $request, $response) {
                    return false;
                }
            ]);
            $this->assertTrue($result);
        }

        $request6 = new Request([], [], [], [
            'sRefreshToken' => $responseData1['refreshToken']
        ]);
        $request6->setMethod("post");
        $request6->server->set("REQUEST_URI", Utils::TEST_REFRESH_TOKEN_PATH_KEY_VALUE_TEST_MIDDLEWARE);
        try {
            (new Middleware)->handle($request6, function (Request $req) {
                $responseObject = new Response();
                return $responseObject->setStatusCode(200)->setContent("");
            });
            $this->assertTrue(false);
        } catch (Exception $e) {
            $result = SuperTokens::handleError($request6, $e, [
                'onUnauthorised' => function ($exception, $request, $response) {
                    return false;
                },
                'onTryRefreshToken' => function ($exception, $request, $response) {
                    return false;
                },
                'onTokenTheftDetected' => function ($sessionHandle, $userId, $request, $response) {
                    return true;
                }
            ]);
            $this->assertTrue($result);
        }

        $request7 = new Request([], [], [], [
            'sAccessToken' => $responseData3['accessToken'],
            'sIdRefreshToken' => $responseData2['idRefreshToken']
        ]);
        $request7->headers->set("anti-csrf", $responseData2['antiCsrf']);
        $response7 = (new Middleware)->handle($request7, function (Request $req) {
            $responseObject = new Response();
            $req->supertokens->revokeSession();
            return $responseObject->setStatusCode(200)->setContent("");
        });
        $responseData4 = Utils::extractInfoFromResponse($response7);
        $this->assertEquals(0, $responseData4['accessTokenExpiry']);
        $this->assertEquals("", $responseData4['accessToken']);
        $this->assertEquals("", $responseData4['refreshToken']);
        $this->assertEquals("", $responseData4['idRefreshToken']);
        $this->assertEquals(true, $responseData4['accessTokenHttpOnly']);
        $this->assertEquals(true, $responseData4['refreshTokenHttpOnly']);
        $this->assertEquals(true, $responseData4['idRefreshTokenHttpOnly']);
        $this->assertEquals(0, $responseData4['refreshTokenExpiry']);
        $this->assertEquals(Utils::TEST_REFRESH_TOKEN_PATH_KEY_VALUE, $responseData4['refreshTokenCookie']->getPath());
        $this->assertEquals("remove", $responseData4['idRefreshTokenFromHeader']);
        $this->assertNull($responseData4['antiCsrf']);

        $request8 = new Request([], [], [], [
            'sRefreshToken' => $responseData2['refreshToken']
        ]);
        $request8->setMethod("post");
        $request8->server->set("REQUEST_URI", Utils::TEST_REFRESH_TOKEN_PATH_KEY_VALUE_TEST_MIDDLEWARE);
        try {
            (new Middleware)->handle($request8, function (Request $req) {
                $responseObject = new Response();
                return $responseObject->setStatusCode(200)->setContent("");
            });
            $this->assertTrue(false);
        } catch (Exception $e) {
            $result = SuperTokens::handleError($request8, $e, [
                'onTryRefreshToken' => function ($exception, $request, $response) {
                    return false;
                },
                'onTokenTheftDetected' => function ($sessionHandle, $userId, $request, $response) {
                    return true;
                }
            ]);
            $this->assertEquals(440, $result->getStatusCode());
            $responseData5 = Utils::extractInfoFromResponse($result);
            $this->assertEquals(0, $responseData5['accessTokenExpiry']);
            $this->assertEquals("", $responseData5['accessToken']);
            $this->assertEquals("", $responseData5['refreshToken']);
            $this->assertEquals("", $responseData5['idRefreshToken']);
            $this->assertEquals(true, $responseData5['accessTokenHttpOnly']);
            $this->assertEquals(true, $responseData5['refreshTokenHttpOnly']);
            $this->assertEquals(true, $responseData5['idRefreshTokenHttpOnly']);
            $this->assertEquals(0, $responseData5['refreshTokenExpiry']);
            $this->assertEquals(Utils::TEST_REFRESH_TOKEN_PATH_KEY_VALUE, $responseData5['refreshTokenPath']);
            $this->assertEquals("remove", $responseData5['idRefreshTokenFromHeader']);
            $this->assertNull($responseData5['antiCsrf']);
        }
    }
}
