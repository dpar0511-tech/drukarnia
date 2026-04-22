<?php

namespace App\Http\Responses;

use App\Enums\SystemRole;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     * @return Response
     */
    public function toResponse($request)
    {
        $user = $request->user();

        if ($user->hasRole(SystemRole::Klient->value)) {
            return redirect()->intended('/portal');
        }

        if ($user->hasRole(SystemRole::Operator->value)) {
            return redirect()->intended('/kanban');
        }

        return redirect()->intended(config('fortify.home'));
    }
}
