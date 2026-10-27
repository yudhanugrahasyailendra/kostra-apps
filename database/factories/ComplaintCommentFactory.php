<?php

namespace Database\Factories;

use App\Models\Complaint;
use App\Models\ComplaintComment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Tepat satu penulis: komentar pengelola (user_id) atau penghuni (resident_id, user_id null).
 *
 * @extends Factory<ComplaintComment>
 */
class ComplaintCommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'complaint_id' => Complaint::factory(),
            'user_id' => User::factory(),
            'resident_id' => null,
            'body' => fake()->sentence(),
        ];
    }
}
