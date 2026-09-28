<?php

namespace App\Policies;

use App\Models\Testimonial;
use App\Models\User;

/**
 * Testimonials/reviews are homepage-builder content (they only ever appear
 * through the testimonials/reviews blocks), so they share the block's own
 * builder.view/builder.edit gate rather than a new permission.
 */
class TestimonialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('builder.view');
    }

    public function view(User $user, Testimonial $testimonial): bool
    {
        return $user->can('builder.view');
    }

    public function create(User $user): bool
    {
        return $user->can('builder.edit');
    }

    public function update(User $user, Testimonial $testimonial): bool
    {
        return $user->can('builder.edit');
    }

    public function delete(User $user, Testimonial $testimonial): bool
    {
        return $user->can('builder.edit');
    }
}
