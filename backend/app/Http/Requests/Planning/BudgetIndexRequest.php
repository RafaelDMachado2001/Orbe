<?php
declare(strict_types=1);
namespace App\Http\Requests\Planning;
use Illuminate\Foundation\Http\FormRequest;
class BudgetIndexRequest extends FormRequest { public function authorize(): bool{return true;} public function rules():array{return ['month'=>['nullable','date_format:Y-m']];} public function month(): string{return $this->input('month',now()->format('Y-m'));} }
