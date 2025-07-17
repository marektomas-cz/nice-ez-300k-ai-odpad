<?php

namespace Tests\Unit;

use App\Models\Script;
use App\Models\Client;
use App\Models\User;
use App\Models\ScriptExecutionLog;
use App\Services\ScriptingService;
use App\Services\Security\ScriptSecurityService;
use App\Services\Scripting\ScriptingApiService;
use App\Services\Scripting\ResourceMonitorService;
use App\Exceptions\ScriptExecutionException;
use App\Exceptions\SecurityViolationException;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class ScriptingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ScriptingService $scriptingService;
    protected ScriptSecurityService $securityService;
    protected ScriptingApiService $apiService;
    protected ResourceMonitorService $resourceMonitor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->securityService = Mockery::mock(ScriptSecurityService::class);
        $this->apiService = Mockery::mock(ScriptingApiService::class);
        $this->resourceMonitor = Mockery::mock(ResourceMonitorService::class);

        $this->scriptingService = new ScriptingService(
            $this->securityService,
            $this->apiService,
            $this->resourceMonitor
        );
    }

    public function test_validates_script_syntax_successfully()
    {
        $validCode = 'const result = "Hello, World!"; return result;';
        
        $result = $this->scriptingService->validateScriptSyntax($validCode);
        
        $this->assertTrue($result['valid']);
        $this->assertArrayNotHasKey('error', $result);
    }

    public function test_validates_script_syntax_with_error()
    {
        $invalidCode = 'const result = "Hello, World!; return result;'; // Missing quote
        
        $result = $this->scriptingService->validateScriptSyntax($invalidCode);
        
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
        $this->assertIsString($result['error']);
    }

    public function test_executes_script_successfully()
    {
        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $script = Script::factory()->create([
            'client_id' => $client->id,
            'code' => 'return "Hello, World!";',
            'is_active' => true,
        ]);

        // Mock dependencies
        $this->securityService->shouldReceive('canExecuteScript')
            ->once()
            ->with($script, $user)
            ->andReturn(true);

        $this->securityService->shouldReceive('hasExceededRateLimit')
            ->once()
            ->with($client)
            ->andReturn(false);

        $this->securityService->shouldReceive('validateScriptContent')
            ->once()
            ->with($script->code)
            ->andReturn([]);

        $this->securityService->shouldReceive('isValidContextVariable')
            ->andReturn(true);

        $this->resourceMonitor->shouldReceive('hasAvailableResources')
            ->once()
            ->andReturn(true);

        $this->resourceMonitor->shouldReceive('startMonitoring')
            ->once();

        $this->resourceMonitor->shouldReceive('getResourceUsage')
            ->once()
            ->andReturn(['memory' => 1024, 'cpu' => 0.1]);

        $this->resourceMonitor->shouldReceive('stopMonitoring')
            ->once();

        $this->apiService->shouldReceive('createSecureApi')
            ->once()
            ->andReturn(Mockery::mock());

        $this->actingAs($user);

        $result = $this->scriptingService->executeScript($script, [], 'manual', $user->id);

        $this->assertInstanceOf(ScriptExecutionLog::class, $result);
        $this->assertEquals('success', $result->status);
        $this->assertEquals($script->id, $result->script_id);
        $this->assertEquals($user->id, $result->executed_by);
    }

    public function test_execution_fails_with_security_violation()
    {
        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $script = Script::factory()->create([
            'client_id' => $client->id,
            'code' => 'eval("malicious code");',
            'is_active' => true,
        ]);

        // Mock security violation
        $this->securityService->shouldReceive('canExecuteScript')
            ->once()
            ->with($script, $user)
            ->andReturn(false);

        $this->resourceMonitor->shouldReceive('startMonitoring')
            ->once();

        $this->resourceMonitor->shouldReceive('stopMonitoring')
            ->once();

        $this->actingAs($user);

        $this->expectException(SecurityViolationException::class);
        $this->expectExceptionMessage('Insufficient permissions to execute script');

        $this->scriptingService->executeScript($script, [], 'manual', $user->id);
    }

    public function test_execution_fails_with_rate_limit_exceeded()
    {
        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $script = Script::factory()->create([
            'client_id' => $client->id,
            'code' => 'return "Hello, World!";',
            'is_active' => true,
        ]);

        // Mock rate limit exceeded
        $this->securityService->shouldReceive('canExecuteScript')
            ->once()
            ->with($script, $user)
            ->andReturn(true);

        $this->securityService->shouldReceive('hasExceededRateLimit')
            ->once()
            ->with($client)
            ->andReturn(true);

        $this->resourceMonitor->shouldReceive('startMonitoring')
            ->once();

        $this->resourceMonitor->shouldReceive('stopMonitoring')
            ->once();

        $this->actingAs($user);

        $this->expectException(SecurityViolationException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        $this->scriptingService->executeScript($script, [], 'manual', $user->id);
    }

    public function test_execution_fails_with_content_validation_error()
    {
        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $script = Script::factory()->create([
            'client_id' => $client->id,
            'code' => 'eval("malicious code");',
            'is_active' => true,
        ]);

        // Mock content validation failure
        $this->securityService->shouldReceive('canExecuteScript')
            ->once()
            ->with($script, $user)
            ->andReturn(true);

        $this->securityService->shouldReceive('hasExceededRateLimit')
            ->once()
            ->with($client)
            ->andReturn(false);

        $this->securityService->shouldReceive('validateScriptContent')
            ->once()
            ->with($script->code)
            ->andReturn(['Forbidden pattern detected: eval']);

        $this->resourceMonitor->shouldReceive('startMonitoring')
            ->once();

        $this->resourceMonitor->shouldReceive('stopMonitoring')
            ->once();

        $this->actingAs($user);

        $this->expectException(SecurityViolationException::class);
        $this->expectExceptionMessage('Script contains security violations');

        $this->scriptingService->executeScript($script, [], 'manual', $user->id);
    }

    public function test_execution_fails_with_insufficient_resources()
    {
        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $script = Script::factory()->create([
            'client_id' => $client->id,
            'code' => 'return "Hello, World!";',
            'is_active' => true,
        ]);

        // Mock insufficient resources
        $this->securityService->shouldReceive('canExecuteScript')
            ->once()
            ->with($script, $user)
            ->andReturn(true);

        $this->securityService->shouldReceive('hasExceededRateLimit')
            ->once()
            ->with($client)
            ->andReturn(false);

        $this->securityService->shouldReceive('validateScriptContent')
            ->once()
            ->with($script->code)
            ->andReturn([]);

        $this->resourceMonitor->shouldReceive('hasAvailableResources')
            ->once()
            ->andReturn(false);

        $this->resourceMonitor->shouldReceive('startMonitoring')
            ->once();

        $this->resourceMonitor->shouldReceive('stopMonitoring')
            ->once();

        $this->actingAs($user);

        $this->expectException(SecurityViolationException::class);
        $this->expectExceptionMessage('Insufficient system resources');

        $this->scriptingService->executeScript($script, [], 'manual', $user->id);
    }

    public function test_gets_execution_stats_successfully()
    {
        $client = Client::factory()->create();
        $script = Script::factory()->create(['client_id' => $client->id]);
        
        // Create some execution logs
        ScriptExecutionLog::factory()->create([
            'script_id' => $script->id,
            'status' => 'success',
            'execution_time' => 1.5,
        ]);
        
        ScriptExecutionLog::factory()->create([
            'script_id' => $script->id,
            'status' => 'failed',
            'execution_time' => 2.0,
        ]);

        $stats = $this->scriptingService->getExecutionStats($script);

        $this->assertIsArray($stats);
        $this->assertEquals(2, $stats['total_executions']);
        $this->assertEquals(1, $stats['successful_executions']);
        $this->assertEquals(1, $stats['failed_executions']);
        $this->assertEquals(1.5, $stats['average_execution_time']);
    }

    public function test_execution_log_is_created_with_correct_data()
    {
        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $script = Script::factory()->create([
            'client_id' => $client->id,
            'code' => 'return "Hello, World!";',
            'is_active' => true,
        ]);

        // Mock dependencies for successful execution
        $this->securityService->shouldReceive('canExecuteScript')
            ->once()
            ->andReturn(true);

        $this->securityService->shouldReceive('hasExceededRateLimit')
            ->once()
            ->andReturn(false);

        $this->securityService->shouldReceive('validateScriptContent')
            ->once()
            ->andReturn([]);

        $this->securityService->shouldReceive('isValidContextVariable')
            ->andReturn(true);

        $this->resourceMonitor->shouldReceive('hasAvailableResources')
            ->once()
            ->andReturn(true);

        $this->resourceMonitor->shouldReceive('startMonitoring')
            ->once();

        $this->resourceMonitor->shouldReceive('getResourceUsage')
            ->once()
            ->andReturn(['memory' => 1024, 'cpu' => 0.1]);

        $this->resourceMonitor->shouldReceive('stopMonitoring')
            ->once();

        $this->apiService->shouldReceive('createSecureApi')
            ->once()
            ->andReturn(Mockery::mock());

        $this->actingAs($user);

        $context = ['test' => 'value'];
        $result = $this->scriptingService->executeScript($script, $context, 'manual', $user->id);

        $this->assertDatabaseHas('script_execution_logs', [
            'id' => $result->id,
            'script_id' => $script->id,
            'client_id' => $client->id,
            'executed_by' => $user->id,
            'status' => 'success',
            'trigger_type' => 'manual',
        ]);

        $this->assertEquals(json_encode($context), $result->execution_context);
        $this->assertEquals($context, $result->trigger_data);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}