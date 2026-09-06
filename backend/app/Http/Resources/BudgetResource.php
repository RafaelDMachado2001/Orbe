<?php
declare(strict_types=1);
namespace App\Http\Resources;
use Illuminate\Http\Resources\Json\JsonResource;
class BudgetResource extends JsonResource { public function toArray($request): array { return ['id'=>$this->id,'category_id'=>$this->category_id,'category'=>['id'=>$this->category->id,'name'=>$this->category->name,'color'=>$this->category->color],'reference_month'=>$this->reference_month->format('Y-m'),'limit_amount'=>(float)$this->limit_amount]; } }
