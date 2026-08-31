<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Ledger\Queries\TransactionOptionsQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionOptionsResource;
use Illuminate\Http\Request;

class TransactionOptionsController extends Controller
{
    public function __invoke(Request $request, TransactionOptionsQuery $query): TransactionOptionsResource
    {
        return new TransactionOptionsResource($query->handle($request->user()->id));
    }
}
