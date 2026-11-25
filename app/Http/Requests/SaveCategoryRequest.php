<?php

namespace App\Http\Requests;

use App\Models\Category;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCategoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:140', 'alpha_dash:ascii', Rule::unique('categories', 'slug')->ignore($this->route('category'))],
            // Two levels only: a parent must be top-level, and a category that has children stays top-level.
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('parent_id'), function (string $attribute, mixed $value, Closure $fail): void {
                $category = $this->route('category');

                if ($category instanceof Category && ((int) $value === $category->id || $category->children()->exists())) {
                    $fail(__('ui.invalid_parent_category'));
                }
            }],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
