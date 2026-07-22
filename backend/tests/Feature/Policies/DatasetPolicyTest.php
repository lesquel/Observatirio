<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Application\Auth\Services\AuthorizationService;
use App\Infrastructure\Persistence\Eloquent\Models\DatasetModel;
use App\Models\User;
use App\Policies\DatasetPolicy;
use Mockery\MockInterface;
use Tests\TestCase;

class DatasetPolicyTest extends TestCase
{
    private DatasetPolicy $policy;
    private AuthorizationService|MockInterface $authService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authService = $this->mock(AuthorizationService::class);
        $this->policy = new DatasetPolicy($this->authService);
    }

    public function test_dept_editor_can_create_dataset_in_own_dept(): void
    {
        $user = new User(['id' => 1, 'rol' => 'EDITOR']);

        $this->authService
            ->shouldReceive('canWriteDataset')
            ->once()
            ->andReturn(true);

        $this->assertTrue($this->policy->create($user, 'dept-1'));
    }

    public function test_dept_admin_can_delete_dataset_from_own_dept(): void
    {
        $user = new User();
        $dataset = new DatasetModel(['departamento_id' => 'dept-1']);

        $this->authService
            ->shouldReceive('canWriteDataset')
            ->once()
            ->andReturn(true);

        $this->assertTrue($this->policy->delete($user, $dataset));
    }

    public function test_lector_cannot_write_any_dataset(): void
    {
        $user = new User();
        $dataset = new DatasetModel(['departamento_id' => 'dept-1']);

        $this->authService
            ->shouldReceive('canWriteDataset')
            ->twice()
            ->andReturn(false);

        $this->assertFalse($this->policy->update($user, $dataset));
        $this->assertFalse($this->policy->delete($user, $dataset));
    }

    public function test_global_admin_bypasses_department_check(): void
    {
        $user = new User();
        $dataset = new DatasetModel(['departamento_id' => 'dept-1']);

        $this->authService
            ->shouldReceive('canWriteDataset')
            ->once()
            ->andReturn(true);

        $this->assertTrue($this->policy->update($user, $dataset));
    }

    public function test_editor_rejected_for_other_dept(): void
    {
        $user = new User();
        $dataset = new DatasetModel(['departamento_id' => 'dept-2']);

        $this->authService
            ->shouldReceive('canWriteDataset')
            ->once()
            ->andReturn(false);

        $this->assertFalse($this->policy->update($user, $dataset));
    }
}
