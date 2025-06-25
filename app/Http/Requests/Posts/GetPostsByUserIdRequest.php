<?php

namespace App\Http\Requests\Posts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class GetPostsByUserIdRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'page_size' => 'integer|min:1',
            'page' => 'integer|min:1',
            'sort_by' => 'string|in:id,title,content,updated_at,created_at',
            'sort_order' => 'string|in:asc,desc',
            'search' => 'string|nullable|max:255',
        ];
    }
}
