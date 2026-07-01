<?php

namespace App\Traits;

trait SerializeAsPiece
{
    public function jsonSerialize(): array
    {
        return [
            'type' => strtolower(class_basename(static::class)),
            ...get_object_vars($this),
        ];
    }
}
