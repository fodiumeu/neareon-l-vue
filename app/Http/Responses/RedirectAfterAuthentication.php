<?php

namespace App\Http\Responses;

use App\Support\NextUserRoute;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RegisterResponse;

class RedirectAfterAuthentication implements LoginResponse, RegisterResponse
{
    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 201);
        }

        return NextUserRoute::redirect($request->user());
    }
}
