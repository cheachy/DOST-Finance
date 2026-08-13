<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The app has no public landing page - '/' redirects to the login screen
     * (routes/web.php). The stock version of this test asserted 200 and had
     * therefore been failing ever since that redirect was added.
     */
    public function test_the_root_url_redirects_a_guest_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}
