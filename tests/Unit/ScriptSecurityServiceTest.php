<?php

namespace Tests\Unit;

use App\Models\Script;
use App\Models\Client;
use App\Models\User;
use App\Services\Security\ScriptSecurityService;
use App\Services\Scripting\ResourceMonitorService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class ScriptSecurityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ScriptSecurityService $securityService;
    protected ResourceMonitorService $resourceMonitor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resourceMonitor = Mockery::mock(ResourceMonitorService::class);
        $this->securityService = new ScriptSecurityService($this->resourceMonitor);
    }

    public function test_can_execute_script_with_valid_permissions()
    {
        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $script = Script::factory()->create([
            'client_id' => $client->id,
            'is_active' => true,
        ]);

        // Mock user permissions
        $user->shouldReceive('can')->with('execute-scripts')->andReturn(true);

        $result = $this->securityService->canExecuteScript($script, $user);

        $this->assertTrue($result);
    }

    public function test_cannot_execute_script_with_no_user()
    {
        $script = Script::factory()->create(['is_active' => true]);

        $result = $this->securityService->canExecuteScript($script, null);

        $this->assertFalse($result);
    }

    public function test_cannot_execute_inactive_script()
    {
        $client = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client->id]);
        $script = Script::factory()->create([
            'client_id' => $client->id,
            'is_active' => false,
        ]);

        $result = $this->securityService->canExecuteScript($script, $user);

        $this->assertFalse($result);
    }

    public function test_cannot_execute_script_from_different_client()
    {
        $client1 = Client::factory()->create();
        $client2 = Client::factory()->create();
        $user = User::factory()->create(['client_id' => $client1->id]);
        $script = Script::factory()->create([
            'client_id' => $client2->id,
            'is_active' => true,
        ]);

        $result = $this->securityService->canExecuteScript($script, $user);

        $this->assertFalse($result);
    }

    public function test_validates_script_content_detects_forbidden_patterns()
    {
        $maliciousCode = 'eval("malicious code"); setTimeout(() => {}, 1000);';

        $issues = $this->securityService->validateScriptContent($maliciousCode);

        $this->assertNotEmpty($issues);
        $this->assertStringContainsString('eval', $issues[0]);
    }

    public function test_validates_script_content_detects_excessive_length()
    {
        $longCode = str_repeat('a', 70000); // Exceeds max length

        $issues = $this->securityService->validateScriptContent($longCode);

        $this->assertNotEmpty($issues);
        $this->assertStringContainsString('exceeds maximum length', $issues[0]);
    }

    public function test_validates_script_content_detects_excessive_nesting()
    {
        $deeplyNestedCode = str_repeat('{', 15) . 'console.log("test");' . str_repeat('}', 15);

        $issues = $this->securityService->validateScriptContent($deeplyNestedCode);

        $this->assertNotEmpty($issues);
        $this->assertStringContainsString('excessive nesting', $issues[0]);
    }

    public function test_validates_script_content_detects_potential_infinite_loops()
    {
        $infiniteLoopCode = 'while(true) { console.log("infinite"); }';

        $issues = $this->securityService->validateScriptContent($infiniteLoopCode);

        $this->assertNotEmpty($issues);
        $this->assertStringContainsString('infinite loop', $issues[0]);
    }

    public function test_validates_script_content_detects_encoded_content()
    {
        $encodedCode = 'const encoded = "SGVsbG8gV29ybGQ="; // Base64 encoded string that is suspicious';

        $issues = $this->securityService->validateScriptContent($encodedCode);

        $this->assertNotEmpty($issues);
        $this->assertStringContainsString('encoded', $issues[0]);
    }

    public function test_validates_script_content_passes_clean_code()
    {
        $cleanCode = 'const result = api.database.query("SELECT * FROM users WHERE active = ?", [true]); return result;';

        $issues = $this->securityService->validateScriptContent($cleanCode);

        $this->assertEmpty($issues);
    }

    public function test_validates_context_variable_with_valid_format()
    {
        $result = $this->securityService->isValidContextVariable('validVariable', 'value');

        $this->assertTrue($result);
    }

    public function test_validates_context_variable_rejects_invalid_format()
    {
        $result = $this->securityService->isValidContextVariable('123invalid', 'value');

        $this->assertFalse($result);
    }

    public function test_validates_context_variable_rejects_reserved_words()
    {
        $result = $this->securityService->isValidContextVariable('api', 'value');

        $this->assertFalse($result);
    }

    public function test_validates_context_variable_rejects_objects()
    {
        $object = new \stdClass();
        $result = $this->securityService->isValidContextVariable('variable', $object);

        $this->assertFalse($result);
    }

    public function test_can_access_database_with_permissions()
    {
        $client = Client::factory()->create([
            'settings' => ['permissions' => ['database.access']]
        ]);
        $script = Script::factory()->create([
            'client_id' => $client->id,
            'configuration' => ['permissions' => ['database' => true]]
        ]);

        $result = $this->securityService->canAccessDatabase($script);

        $this->assertTrue($result);
    }

    public function test_cannot_access_database_without_client_permission()
    {
        $client = Client::factory()->create([
            'settings' => ['permissions' => []]
        ]);
        $script = Script::factory()->create([
            'client_id' => $client->id,
            'configuration' => ['permissions' => ['database' => true]]
        ]);

        $result = $this->securityService->canAccessDatabase($script);

        $this->assertFalse($result);
    }

    public function test_can_access_url_with_allowed_domain()
    {
        $client = Client::factory()->create([
            'settings' => ['permissions' => ['http.access']]
        ]);
        $script = Script::factory()->create([
            'client_id' => $client->id,
            'configuration' => ['permissions' => ['http' => true]]
        ]);

        $result = $this->securityService->canAccessUrl($script, 'https://api.example.com/data');

        $this->assertTrue($result);
    }

    public function test_cannot_access_url_with_invalid_format()
    {
        $client = Client::factory()->create([
            'settings' => ['permissions' => ['http.access']]
        ]);
        $script = Script::factory()->create([
            'client_id' => $client->id,
            'configuration' => ['permissions' => ['http' => true]]
        ]);

        $result = $this->securityService->canAccessUrl($script, 'invalid-url');

        $this->assertFalse($result);
    }

    public function test_cannot_access_internal_ip()
    {
        $client = Client::factory()->create([
            'settings' => ['permissions' => ['http.access']]
        ]);
        $script = Script::factory()->create([
            'client_id' => $client->id,
            'configuration' => ['permissions' => ['http' => true]]
        ]);

        $result = $this->securityService->canAccessUrl($script, 'http://192.168.1.1/api');

        $this->assertFalse($result);
    }

    public function test_can_dispatch_event_with_permissions()
    {
        $client = Client::factory()->create([
            'settings' => ['permissions' => ['events.dispatch']]
        ]);
        $script = Script::factory()->create([
            'client_id' => $client->id,
            'configuration' => ['permissions' => ['events' => true]]
        ]);

        $result = $this->securityService->canDispatchEvent($script, 'script.custom.event');

        $this->assertTrue($result);
    }

    public function test_cannot_dispatch_forbidden_event()
    {
        $client = Client::factory()->create([
            'settings' => ['permissions' => ['events.dispatch']]
        ]);
        $script = Script::factory()->create([
            'client_id' => $client->id,
            'configuration' => ['permissions' => ['events' => true]]
        ]);

        $result = $this->securityService->canDispatchEvent($script, 'system.critical.event');

        $this->assertFalse($result);
    }

    public function test_generates_security_report()
    {
        $client = Client::factory()->create();
        $script = Script::factory()->create([
            'client_id' => $client->id,
            'code' => 'return "Hello, World!";',
            'configuration' => ['permissions' => ['database' => true]]
        ]);

        $report = $this->securityService->generateSecurityReport($script);

        $this->assertIsArray($report);
        $this->assertArrayHasKey('script_id', $report);
        $this->assertArrayHasKey('security_score', $report);
        $this->assertArrayHasKey('risk_level', $report);
        $this->assertArrayHasKey('issues', $report);
        $this->assertArrayHasKey('recommendations', $report);
        $this->assertArrayHasKey('generated_at', $report);
        $this->assertEquals($script->id, $report['script_id']);
    }

    public function test_calculates_security_score_correctly()
    {
        $client = Client::factory()->create();
        $script = Script::factory()->create([
            'client_id' => $client->id,
            'code' => 'return "Hello, World!";',
            'configuration' => ['permissions' => []]
        ]);

        $score = $this->securityService->getSecurityScore($script);

        $this->assertIsFloat($score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}