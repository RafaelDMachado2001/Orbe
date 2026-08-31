<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Banking\Models\Account;
use App\Domain\Cards\Actions\CloseInvoiceAction;
use App\Domain\Cards\Actions\PayInvoiceAction;
use App\Domain\Cards\Actions\UndoInvoicePaymentAction;
use App\Domain\Cards\Models\Invoice;
use App\Domain\Cards\Queries\InvoiceDetailQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cards\PayInvoiceRequest;
use App\Http\Resources\InvoiceDetailResource;
use Carbon\CarbonImmutable;

class InvoiceController extends Controller
{
    public function show(Invoice $invoice, InvoiceDetailQuery $query): InvoiceDetailResource
    {
        return $this->present($invoice, $query);
    }

    public function pay(
        PayInvoiceRequest $request,
        Invoice $invoice,
        PayInvoiceAction $action,
        InvoiceDetailQuery $query,
    ): InvoiceDetailResource {
        $account = Account::query()->findOrFail($request->integer('account_id'));

        $action->handle($invoice, $account, $request->amount(), $request->paidAt());

        return $this->present($invoice->refresh(), $query);
    }

    public function undoPayment(
        Invoice $invoice,
        UndoInvoicePaymentAction $action,
        InvoiceDetailQuery $query,
    ): InvoiceDetailResource {
        return $this->present($action->handle($invoice), $query);
    }

    public function close(
        Invoice $invoice,
        CloseInvoiceAction $action,
        InvoiceDetailQuery $query,
    ): InvoiceDetailResource {
        return $this->present($action->handleOrFail($invoice), $query);
    }

    private function present(Invoice $invoice, InvoiceDetailQuery $query): InvoiceDetailResource
    {
        $invoice->loadMissing('creditCard');

        return new InvoiceDetailResource([
            'invoice' => $invoice,
            'items' => $query->handle($invoice),
            'payments' => $query->payments($invoice),
            'today' => CarbonImmutable::now(),
        ]);
    }
}
