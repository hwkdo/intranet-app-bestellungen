<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBestellungen\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Hwkdo\IntranetAppBestellungen\Http\Resources\ApiUserResource;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request): ApiUserResource
    {
        return new ApiUserResource($request->user());
    }
}
