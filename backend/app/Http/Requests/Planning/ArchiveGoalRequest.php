<?php
declare(strict_types=1);
namespace App\Http\Requests\Planning;
use Illuminate\Foundation\Http\FormRequest;
class ArchiveGoalRequest extends FormRequest { public function authorize():bool{return true;} /** @return array<string,mixed> */ public function rules():array{return ['is_archived'=>['required','boolean']];} public function isArchived():bool{return $this->boolean('is_archived');} }
