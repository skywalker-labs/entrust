<?php

declare(strict_types=1);

namespace Skywalker\Entrust\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;

class EntrustAbility
{
	public const DELIMITER = '|';

	/**
	 * Creates a new instance of the middleware.
	 */
	public function __construct(protected Guard $auth) {}

	/**
	 * Handle an incoming request.
	 */
	public function handle(\Illuminate\Http\Request $request, Closure $next, mixed $roles, mixed $permissions, mixed $validateAll = false): mixed
	{
		if (!is_array($roles)) {
			$roles = $roles ?? '';
			$roles = explode(self::DELIMITER, (string) $roles);
		}

		if (!is_array($permissions)) {
			$permissions = $permissions ?? '';
			$permissions = explode(self::DELIMITER, (string) $permissions);
		}

		if (!is_bool($validateAll)) {
			$validateAll = filter_var($validateAll, FILTER_VALIDATE_BOOLEAN);
		}

		if ($this->auth->guest() || !$request->user()?->ability($roles, $permissions, ['validate_all' => $validateAll])) {
			abort(403);
		}

		return $next($request);
	}
}
