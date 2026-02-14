<?php

declare(strict_types=1);

namespace Skywalker\Entrust\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;

class EntrustRole
{
	public const DELIMITER = '|';

	/**
	 * Creates a new instance of the middleware.
	 */
	public function __construct(protected Guard $auth) {}

	/**
	 * Handle an incoming request.
	 */
	public function handle(\Illuminate\Http\Request $request, Closure $next, mixed $roles): mixed
	{
		$requireAll = false;
		if (!is_array($roles)) {
			$roles = $roles ?? '';
			if (str_contains((string) $roles, '&')) {
				$roles = explode('&', (string) $roles);
				$requireAll = true;
			} else {
				$roles = explode(self::DELIMITER, (string) $roles);
			}
		}

		if ($this->auth->guest() || !$request->user()?->hasRole($roles, $requireAll)) {
			abort(403);
		}

		return $next($request);
	}
}
