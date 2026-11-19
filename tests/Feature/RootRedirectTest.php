<?php

namespace Tests\Feature;

use Tests\TestCase;

class RootRedirectTest extends TestCase
{
    public function test_guests_are_redirected_from_the_root_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }
}
