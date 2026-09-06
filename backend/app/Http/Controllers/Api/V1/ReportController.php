<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Reports\Actions\BuildAnnualReportAction;
use App\Domain\Reports\Support\AnnualReportCsvExporter;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnnualReportRequest;
use App\Http\Requests\ExportAnnualReportRequest;
use App\Http\Resources\AnnualReportResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function annual(AnnualReportRequest $request, BuildAnnualReportAction $action): AnnualReportResource
    {
        return new AnnualReportResource($action->handle($request->user()->id, $request->year()));
    }

    public function exportAnnual(
        ExportAnnualReportRequest $request,
        BuildAnnualReportAction $action,
        AnnualReportCsvExporter $csvExporter,
    ): Response|StreamedResponse {
        $report = $action->handle($request->user()->id, $request->year());

        if ($request->exportFormat() === 'pdf') {
            return Pdf::loadView('reports.annual', ['report' => $report])
                ->download("relatorio-anual-{$report->year}.pdf");
        }

        $csv = $csvExporter->handle($report);

        return response()->streamDownload(
            static function () use ($csv): void {
                echo $csv;
            },
            "relatorio-anual-{$report->year}.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
