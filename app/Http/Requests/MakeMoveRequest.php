<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
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
            'from.*' => ['between:0,7']
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $pieces = $this->route('board')->pieces;

            $legalMoves = $pieces->where('x', $this->array('from')[0])->where('y', $this->array('from')[1])->first()->getSemiLegalMoves($pieces);

            if($legalMoves->filter(fn(array $move) => $move[0] === $this->array('to')[0] && $move[1] === $this->array('to')[1])->isEmpty()) {
                $validator->errors()->add('to', 'Illegal move');
            };
        });
    }
}
