<?php
declare(strict_types=1);
namespace App\Http\Requests;
class ExportAnnualReportRequest extends AnnualReportRequest
{
    /** @return array<string,mixed> */
    public function rules(): array { return [...parent::rules(), 'format'=>['required','in:csv,pdf']]; }
    public function exportFormat(): string { return $this->string('format')->toString(); }
}
