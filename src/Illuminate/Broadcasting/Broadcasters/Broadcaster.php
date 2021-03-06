<?php

namespace Illuminate\Broadcasting\Broadcasters;

use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Contracts\Broadcasting\Broadcaster as BroadcasterContract;

abstract class Broadcaster implements BroadcasterContract
{
    /**
     * The registered channel authenticators.
     *
     * @var array
     */
    protected $channels = [];

    /**
     * {@inheritdoc}
     */
    abstract public function broadcast(array $channels, $event, array $payload = []);

    /**
     * Register a channel authenticator.
     *
     * @param  string  $channel
     * @param  callable  $callback
     * @return $this
     */
    public function auth($channel, callable $callback)
    {
        $this->channels[$channel] = $callback;

        return $this;
    }

    /**
     * Authenticate the incoming request for a given channel.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function check($request)
    {
        $channel = str_replace(['private-', 'presence-'], '', $request->channel_name);

        foreach ($this->channels as $pattern => $callback) {
            if (! Str::is($pattern, $channel)) {
                continue;
            }

            $parameters = $this->extractAuthParameters($pattern, $channel);

            if ($result = $callback($request->user(), ...$parameters)) {
                return $this->validAuthenticationResponse($request, $result);
            }
        }

        throw new HttpException(403);
    }

    /**
     * Extract the parameters from the given pattern and channel.
     *
     * @param  string  $pattern
     * @param  string  $channel
     * @return array
     */
    protected function extractAuthParameters($pattern, $channel)
    {
        if (! Str::contains($pattern, '*')) {
            return [];
        }

        $pattern = str_replace('\*', '([^\.]+)', preg_quote($pattern));

        if (preg_match('/^'.$pattern.'/', $channel, $keys)) {
            array_shift($keys);

            return $keys;
        }

        return [];
    }

    /**
     * Return the valid Pusher authentication response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $result
     * @return mixed
     */
    protected function validAuthenticationResponse($request, $result)
    {
        if (is_bool($result)) {
            return json_encode($result);
        }

        return json_encode(['channel_data' => [
            'user_id' => $request->user()->getKey(),
            'user_info' => $result,
        ]]);
    }
}
