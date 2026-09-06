<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Banking\Queries\AccountOptionsQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\AccountOptionsResource;
use Illuminate\Http\Request;

class AccountOptionsController extends Controller
{
    public function __invoke(Request $request, AccountOptionsQuery $query): AccountOptionsResource
    {
        return new AccountOptionsResource($query->handle($request->user()->id));
    }
}
