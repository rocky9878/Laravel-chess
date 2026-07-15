<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MakeMoveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'to' => ['required', 'array', 'size:2'],
            'to.*' => ['between:0,7'],
            'from' => ['required', 'array', 'size:2'],
            'from.*' => ['between:0,7'],
            'promotion' => ['in:queen,knight,rook,bishop', Rule::excludeUnless(fn() => ($this->from[1] === 6 || $this->from[1] === 1) && ($this->to[1] === 7 || $this->to[1] === 0)), 'nullable']
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $legalMoves = $this->route('board')->pieces->flatMap(fn($piece) => $piece->legalMoves);

            if($legalMoves->filter(fn(array $move) => $move[0] === $this->array('to')[0] && $move[1] === $this->array('to')[1])->isEmpty()) {
                $validator->errors()->add('to', 'Illegal move');
            };
        });
    }
}

