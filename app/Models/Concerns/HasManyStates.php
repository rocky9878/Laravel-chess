<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\State;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 */
trait HasManyStates
{
    /**
     * Get the associated states
     *
     * @return HasMany<State, $this>
     */
    public function states(): HasMany
    {
        return $this->hasMany(State::class);
    }
}
