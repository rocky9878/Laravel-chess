<?php

namespace App\Http\Requests;

use App\Enums\Colour;
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
        $board = $this->route('board');

        $expectedPlayer = $board->position->toMove === Colour::WHITE ? $board->white : $board->black;

        return $this->user()?->id === $expectedPlayer;
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
            $pos = $this->route('board')->position;
            $legalMoves = $pos->legalMovesFor($pos->pieceAt($this->array('from')[0], $this->array('from')[1]));

            if($legalMoves->filter(fn(array $move) => $move[0] === $this->array('to')[0] && $move[1] === $this->array('to')[1])->isEmpty()) {
                $validator->errors()->add('to', 'Illegal move');
            };
        });
    }
}

