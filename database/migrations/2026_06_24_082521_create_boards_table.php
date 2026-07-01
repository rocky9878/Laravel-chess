<?php

use App\Enums\State;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('boards', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class, 'white')->nullable();
            $table->foreignIdFor(User::class, 'black')->nullable();
            $table->enum('state', State::cases())->default(State::ACTIVE->value);
            $table->timestamps();
        });

        // one of the users must be set
        DB::statement('ALTER TABLE boards ADD CONSTRAINT CHK_user_isset CHECK (NOT(`white` IS NULL AND `black` IS NULL));');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boards');
    }
};
