<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Ledger\Actions\CreateCategoryAction;
use App\Domain\Ledger\Actions\DeleteCategoryAction;
use App\Domain\Ledger\Actions\UpdateCategoryAction;
use App\Domain\Ledger\Models\Category;
use App\Domain\Ledger\Queries\CategoriesPageQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Categories\CategoryIndexRequest;
use App\Http\Requests\Categories\DeleteCategoryRequest;
use App\Http\Requests\Categories\StoreCategoryRequest;
use App\Http\Requests\Categories\UpdateCategoryRequest;
use App\Http\Resources\CategoriesPageResource;
use App\Http\Resources\CategoryResource;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(CategoryIndexRequest $request, CategoriesPageQuery $query): CategoriesPageResource
    {
        return new CategoriesPageResource(
            $query->handle($request->user()->id, $request->month()),
        );
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category);
    }

    public function store(StoreCategoryRequest $request, CreateCategoryAction $action): JsonResponse
    {
        return (new CategoryResource($action->handle($request->user(), $request->toData())))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateCategoryRequest $request,
        Category $category,
        UpdateCategoryAction $action,
    ): CategoryResource {
        return new CategoryResource($action->handle($category, $request->toData()));
    }

    public function destroy(
        DeleteCategoryRequest $request,
        Category $category,
        DeleteCategoryAction $action,
    ): JsonResponse {
        $action->handle($category, $request->destination());

        return response()->json(['message' => 'Categoria excluída.']);
    }
}
