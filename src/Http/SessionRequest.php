<?php


namespace SuperTokens\Http;

use Illuminate\Http\Request;
use SuperTokens\Session;

class SessionRequest extends Request
{
    /**
     * @var Session
     */
    public $supertokenSession;

    public function __construct(array $query = [], array $request = [], array $attributes = [], array $cookies = [], array $files = [], array $server = [], $content = null, $session = null)
    {
        parent::__construct($query, $request, $attributes, $cookies, $files, $server, $content);
        if (isset($session)) {
            $this->supertokenSession = $session;
        }
    }

    /**
     * @param Request $request
     * @param Session $session
     * @return SessionRequest
     */
    public static function attachSession(Request $request, Session $session)
    {
        return new SessionRequest($request->query->all(), $request->post(), $request->attributes->all(), $request->cookies->all(), $request->files->all(), $request->server->all(), $request->content, $session);
    }
}
