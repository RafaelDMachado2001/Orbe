<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Forecast\Actions\ForecastService;
use App\Http\Controllers\Controller;
use App\Http\Requests\ForecastRequest;
use App\Http\Resources\ForecastResource;

class ForecastController extends Controller
{
    public function __invoke(ForecastRequest $r, ForecastService $s): ForecastResource
    {
        return new ForecastResource($s->handle($r->user()->id, $r->horizon(), $r->from()));
    }
}
