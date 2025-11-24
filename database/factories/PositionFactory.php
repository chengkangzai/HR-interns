<?php

namespace Database\Factories;

use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class PositionFactory extends Factory
{
    protected $model = Position::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->jobTitle(),
            'description' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement(['open', 'closed']),
            'type' => $this->faker->randomElement(['intern', 'contract', 'full_time', 'part_time']),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
