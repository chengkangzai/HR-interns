<?php

namespace Database\Factories;

use App\Models\Email;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class EmailFactory extends Factory
{
    protected $model = Email::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'title' => $this->faker->sentence(),
            'body' => $this->faker->paragraph(),
            'sort' => $this->faker->numberBetween(1, 10),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
