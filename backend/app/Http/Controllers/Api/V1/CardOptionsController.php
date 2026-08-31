<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Cards\Queries\CardOptionsQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\CardOptionsResource;
use Illuminate\Http\Request;

class CardOptionsController extends Controller
{
    public function __invoke(Request $request, CardOptionsQuery $query): CardOptionsResource
    {
        return new CardOptionsResource($query->handle($request->user()->id));
    }
}
