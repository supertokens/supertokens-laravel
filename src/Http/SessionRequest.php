<?php


namespace SuperTokens\Http;

use Illuminate\Http\Request;
use SuperTokens\SessionMiddleware;

class SessionRequest extends Request
{
    /**
     * @var SessionMiddleware
     */
    public $supertokenSession;

    public function __construct(SessionMiddleware $session, array $query = [], array $request = [], array $attributes = [], array $cookies = [], array $files = [], array $server = [], $content = null)
    {
        parent::__construct($query, $request, $attributes, $cookies, $files, $server, $content);
        $this->supertokenSession = $session;
    }

    /**
     * @param Request $request
     * @param SessionMiddleware $session
     * @return SessionRequest
     */
    public static function attachSession(Request $request, SessionMiddleware $session)
    {
        return new SessionRequest($session, $request->query->all(), $request->post(), $request->attributes->all(), $request->cookies->all(), $request->files->all(), $request->server->all(), $request->content);
    }
}
