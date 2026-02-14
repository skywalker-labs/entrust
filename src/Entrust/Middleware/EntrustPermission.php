<?php

declare(strict_types=1);

namespace Skywalker\Entrust\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;

class EntrustPermission
{
	public const DELIMITER = '|';

	/**
	 * Creates a new instance of the middleware.
	 */
	public function __construct(protected Guard $auth) {}

	/**
	 * Handle an incoming request.
	 */
	public function handle(\Illuminate\Http\Request $request, Closure $next, mixed $permissions): mixed
	{
		$requireAll = false;
		if (!is_array($permissions)) {
			$permissions = $permissions ?? '';
			if (str_contains((string) $permissions, '&')) {
				$permissions = explode('&', (string) $permissions);
				$requireAll = true;
			} else {
				$permissions = explode(self::DELIMITER, (string) $permissions);
			}
		}

		if ($this->auth->guest() || !$request->user()?->can($permissions, $requireAll)) {
			abort(403);
		}

		return $next($request);
	}
}
