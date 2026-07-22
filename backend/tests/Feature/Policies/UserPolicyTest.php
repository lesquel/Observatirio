<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Application\Auth\Services\AuthorizationService;
use App\Models\User;
use App\Policies\UserPolicy;
use Mockery\MockInterface;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    private UserPolicy $policy;
    private AuthorizationService|MockInterface $authService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authService = $this->mock(AuthorizationService::class);
        $this->policy = new UserPolicy($this->authService);
    }

    public function test_non_admin_cannot_list_users(): void
    {
        $user = new User();

        $this->authService
            ->shouldReceive('canManageUsers')
            ->once()
            ->andReturn(false);

        $this->assertFalse($this->policy->viewAny($user));
    }

    public function test_admin_can_list_users(): void
    {
        $user = new User(['rol' => 'ADMIN']);

        $this->authService
            ->shouldReceive('canManageUsers')
            ->once()
            ->andReturn(true);

        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_non_admin_cannot_create_users(): void
    {
        $user = new User(['rol' => 'USER']);

        $this->authService
            ->shouldReceive('canManageUsers')
            ->once()
            ->andReturn(false);

        $this->assertFalse($this->policy->create($user));
    }

    public function test_non_admin_cannot_update_users(): void
    {
        $user = new User(['rol' => 'EDITOR']);
        $targetUser = new User(['rol' => 'USER']);

        $this->authService
            ->shouldReceive('canManageUsers')
            ->once()
            ->andReturn(false);

        $this->assertFalse($this->policy->update($user, $targetUser));
    }

    public function test_admin_can_delete_users(): void
    {
        $user = new User(['rol' => 'ADMIN']);
        $targetUser = new User(['rol' => 'USER']);

        $this->authService
            ->shouldReceive('canManageUsers')
            ->once()
            ->andReturn(true);

        $this->assertTrue($this->policy->delete($user, $targetUser));
    }
}
