<?php
declare(strict_types=1);
namespace App\Http\Requests\Planning;
use App\Domain\Planning\DTOs\GoalData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
abstract class WriteGoalRequest extends FormRequest
{
 public function authorize(): bool { return true; }
 /** @return array<string,mixed> */
 public function rules(): array { $id=$this->user()->id; return ['name'=>['required','string','min:2','max:80'],'target_amount'=>['required','numeric','gt:0'],'initial_amount'=>[$this->isCreating()?'nullable':'prohibited','numeric','min:0'],'deadline'=>['nullable','date'],'account_id'=>['nullable','integer',Rule::exists('accounts','id')->where('user_id',$id)]]; }
 /** @return array<string,string> */
 public function messages(): array { return ['name.required'=>'Dê um nome à meta.','target_amount.required'=>'Informe o valor alvo.','target_amount.gt'=>'Informe um valor alvo maior que zero.','initial_amount.prohibited'=>'O valor inicial não muda depois da criação. Use "Novo aporte" para fazer o progresso andar.','account_id.exists'=>'Escolha uma conta da sua lista.']; }
 public function toData(): GoalData { return new GoalData(trim($this->string('name')->toString()),(string) round((float) $this->input('target_amount'),2),$this->isCreating()&&$this->filled('initial_amount')?(string) round((float) $this->input('initial_amount'),2):null,$this->input('deadline'),$this->integer('account_id')?:null); }
 private function isCreating(): bool { return $this->route('goal')===null; }
}
