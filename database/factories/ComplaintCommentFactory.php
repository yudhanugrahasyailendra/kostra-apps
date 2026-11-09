<?php

namespace Database\Factories;

use App\Models\ComplaintComment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * complaint_id dan penulis (user_id atau resident_id) diberikan pemanggil.
 *
 * @extends Factory<ComplaintComment>
 */
class ComplaintCommentFactory extends Factory
{
    public function definition(): array
    {
        return ['body' => fake()->sentence()];
    }
}
