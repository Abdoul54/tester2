<?php
// app/Http/Requests/Comments/GetCommentsRequest.php

namespace App\Http\Requests\Comments;

use Illuminate\Foundation\Http\FormRequest;

class GetCommentsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Comments are publicly readable
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'per_page' => 'sometimes|integer|min:1|max:50',
            'page' => 'sometimes|integer|min:1',
            'sort_by' => 'sometimes|string|in:created_at,likes_count,replies_count',
            'sort_order' => 'sometimes|string|in:asc,desc',
            'load_more' => 'sometimes|boolean',
            'last_comment_id' => 'sometimes|integer|exists:comments,id',
            'total_loaded' => 'sometimes|integer|min:0', // Track how many comments have been loaded so far
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // If load_more is true, last_comment_id should be provided (except for first load)
            if ($this->boolean('load_more') && $this->has('last_comment_id') && !$this->get('last_comment_id')) {
                $validator->errors()->add('last_comment_id', 'Last comment ID is required for load more functionality.');
            }

            // If using load more, per_page should be reasonable (not too high)
            if ($this->boolean('load_more') && $this->get('per_page', 15) > 30) {
                $validator->errors()->add('per_page', 'Per page limit for load more should not exceed 30.');
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'per_page.integer' => 'The per page value must be a number.',
            'per_page.min' => 'The per page value must be at least 1.',
            'per_page.max' => 'The per page value cannot exceed 50.',
            'page.integer' => 'The page value must be a number.',
            'page.min' => 'The page value must be at least 1.',
            'sort_by.in' => 'The sort by field must be one of: created_at, likes_count, replies_count.',
            'sort_order.in' => 'The sort order must be either asc or desc.',
            'load_more.boolean' => 'The load more parameter must be true or false.',
            'last_comment_id.exists' => 'The specified comment does not exist.',
            'total_loaded.min' => 'The total loaded count cannot be negative.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'per_page' => 'comments per page',
            'sort_by' => 'sort field',
            'sort_order' => 'sort direction',
            'load_more' => 'load more flag',
            'last_comment_id' => 'last comment ID',
            'total_loaded' => 'total loaded count',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // Set default values
        $this->merge([
            'per_page' => $this->get('per_page', 15),
            'sort_by' => $this->get('sort_by', 'created_at'),
            'sort_order' => $this->get('sort_order', 'desc'),
            'load_more' => $this->boolean('load_more', false),
        ]);

        // Ensure reasonable limits for load more
        if ($this->boolean('load_more')) {
            $this->merge([
                'per_page' => min($this->get('per_page', 15), 30)
            ]);
        }
    }
}
