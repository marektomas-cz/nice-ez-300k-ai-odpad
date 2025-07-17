<?php

namespace Tests\Feature;

use App\Models\Script;
use App\Models\Client;
use App\Models\User;
use App\Models\ScriptExecutionLog;
use App\Services\ScriptingService;
use App\Services\Security\ScriptSecurityService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class ScriptControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Client $client;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Client::factory()->create();
        $this->user = User::factory()->create(['client_id' => $this->client->id]);
    }

    public function test_index_displays_scripts_for_authenticated_user()
    {
        $script = Script::factory()->create([
            'client_id' => $this->client->id,
            'name' => 'Test Script',
        ]);

        $this->actingAs($this->user)
            ->get(route('scripts.index'))
            ->assertStatus(200)
            ->assertSee('Test Script');
    }

    public function test_index_filters_scripts_by_client()
    {
        $otherClient = Client::factory()->create();
        $otherScript = Script::factory()->create([
            'client_id' => $otherClient->id,
            'name' => 'Other Client Script',
        ]);

        $myScript = Script::factory()->create([
            'client_id' => $this->client->id,
            'name' => 'My Script',
        ]);

        $this->actingAs($this->user)
            ->get(route('scripts.index'))
            ->assertStatus(200)
            ->assertSee('My Script')
            ->assertDontSee('Other Client Script');
    }

    public function test_create_displays_form_for_authorized_user()
    {
        $this->actingAs($this->user)
            ->get(route('scripts.create'))
            ->assertStatus(200)
            ->assertSee('Create Script');
    }

    public function test_store_creates_script_with_valid_data()
    {
        $scriptData = [
            'name' => 'Test Script',
            'description' => 'Test Description',
            'code' => 'return "Hello, World!";',
            'language' => 'javascript',
            'is_active' => true,
            'configuration' => [],
            'tags' => ['test', 'example'],
        ];

        $this->actingAs($this->user)
            ->post(route('scripts.store'), $scriptData)
            ->assertRedirect();

        $this->assertDatabaseHas('scripts', [
            'name' => 'Test Script',
            'client_id' => $this->client->id,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_store_validates_required_fields()
    {
        $this->actingAs($this->user)
            ->post(route('scripts.store'), [])
            ->assertSessionHasErrors(['name', 'code']);
    }

    public function test_store_validates_script_syntax()
    {
        $scriptData = [
            'name' => 'Test Script',
            'code' => 'invalid javascript syntax {',
            'language' => 'javascript',
        ];

        $this->actingAs($this->user)
            ->post(route('scripts.store'), $scriptData)
            ->assertSessionHasErrors(['code']);
    }

    public function test_store_validates_security_issues()
    {
        $scriptData = [
            'name' => 'Malicious Script',
            'code' => 'eval("malicious code");',
            'language' => 'javascript',
        ];

        $this->actingAs($this->user)
            ->post(route('scripts.store'), $scriptData)
            ->assertSessionHasErrors(['code']);
    }

    public function test_show_displays_script_details()
    {
        $script = Script::factory()->create([
            'client_id' => $this->client->id,
            'name' => 'Test Script',
        ]);

        $this->actingAs($this->user)
            ->get(route('scripts.show', $script))
            ->assertStatus(200)
            ->assertSee('Test Script');
    }

    public function test_show_prevents_access_to_other_clients_scripts()
    {
        $otherClient = Client::factory()->create();
        $otherScript = Script::factory()->create([
            'client_id' => $otherClient->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('scripts.show', $otherScript))
            ->assertStatus(403);
    }

    public function test_edit_displays_form_for_owned_script()
    {
        $script = Script::factory()->create([
            'client_id' => $this->client->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('scripts.edit', $script))
            ->assertStatus(200)
            ->assertSee('Edit Script');
    }

    public function test_update_modifies_script_with_valid_data()
    {
        $script = Script::factory()->create([
            'client_id' => $this->client->id,
            'name' => 'Original Name',
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'description' => 'Updated Description',
            'code' => 'return "Updated!";',
            'language' => 'javascript',
            'is_active' => false,
        ];

        $this->actingAs($this->user)
            ->put(route('scripts.update', $script), $updateData)
            ->assertRedirect();

        $this->assertDatabaseHas('scripts', [
            'id' => $script->id,
            'name' => 'Updated Name',
            'updated_by' => $this->user->id,
        ]);
    }

    public function test_destroy_deletes_script()
    {
        $script = Script::factory()->create([
            'client_id' => $this->client->id,
        ]);

        $this->actingAs($this->user)
            ->delete(route('scripts.destroy', $script))
            ->assertRedirect();

        $this->assertSoftDeleted('scripts', [
            'id' => $script->id,
        ]);
    }

    public function test_execute_runs_script_successfully()
    {
        $script = Script::factory()->create([
            'client_id' => $this->client->id,
            'code' => 'return "Hello, World!";',
        ]);

        // Mock the scripting service
        $scriptingService = Mockery::mock(ScriptingService::class);
        $executionLog = ScriptExecutionLog::factory()->make([
            'script_id' => $script->id,
            'status' => 'success',
            'output' => 'Hello, World!',
        ]);

        $scriptingService->shouldReceive('executeScript')
            ->once()
            ->with($script, [], 'manual', $this->user->id)
            ->andReturn($executionLog);

        $this->app->instance(ScriptingService::class, $scriptingService);

        $this->actingAs($this->user)
            ->postJson(route('scripts.execute', $script))
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Script executed successfully',
            ]);
    }

    public function test_execute_handles_execution_errors()
    {
        $script = Script::factory()->create([
            'client_id' => $this->client->id,
        ]);

        // Mock the scripting service to throw an exception
        $scriptingService = Mockery::mock(ScriptingService::class);
        $scriptingService->shouldReceive('executeScript')
            ->once()
            ->andThrow(new \Exception('Script execution failed'));

        $this->app->instance(ScriptingService::class, $scriptingService);

        $this->actingAs($this->user)
            ->postJson(route('scripts.execute', $script))
            ->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Script execution failed',
            ]);
    }

    public function test_validate_syntax_returns_validation_results()
    {
        $scriptingService = Mockery::mock(ScriptingService::class);
        $securityService = Mockery::mock(ScriptSecurityService::class);

        $scriptingService->shouldReceive('validateScriptSyntax')
            ->once()
            ->with('return "Hello, World!";')
            ->andReturn(['valid' => true]);

        $securityService->shouldReceive('validateScriptContent')
            ->once()
            ->with('return "Hello, World!";')
            ->andReturn([]);

        $this->app->instance(ScriptingService::class, $scriptingService);
        $this->app->instance(ScriptSecurityService::class, $securityService);

        $this->actingAs($this->user)
            ->postJson(route('scripts.validate-syntax'), [
                'code' => 'return "Hello, World!";',
            ])
            ->assertStatus(200)
            ->assertJson([
                'syntax_valid' => true,
                'security_valid' => true,
            ]);
    }

    public function test_execution_history_returns_paginated_results()
    {
        $script = Script::factory()->create([
            'client_id' => $this->client->id,
        ]);

        ScriptExecutionLog::factory()->count(5)->create([
            'script_id' => $script->id,
            'client_id' => $this->client->id,
        ]);

        $this->actingAs($this->user)
            ->getJson(route('scripts.execution-history', $script))
            ->assertStatus(200)
            ->assertJsonStructure([
                'executions' => [
                    '*' => [
                        'id',
                        'status',
                        'execution_time',
                        'created_at',
                    ],
                ],
                'pagination' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    public function test_security_report_returns_script_analysis()
    {
        $script = Script::factory()->create([
            'client_id' => $this->client->id,
        ]);

        $securityService = Mockery::mock(ScriptSecurityService::class);
        $securityService->shouldReceive('generateSecurityReport')
            ->once()
            ->with($script)
            ->andReturn([
                'script_id' => $script->id,
                'security_score' => 85.0,
                'risk_level' => 'low',
                'issues' => [],
                'recommendations' => [],
                'generated_at' => now()->toISOString(),
            ]);

        $this->app->instance(ScriptSecurityService::class, $securityService);

        $this->actingAs($this->user)
            ->getJson(route('scripts.security-report', $script))
            ->assertStatus(200)
            ->assertJson([
                'script_id' => $script->id,
                'security_score' => 85.0,
                'risk_level' => 'low',
            ]);
    }

    public function test_clone_creates_copy_of_script()
    {
        $script = Script::factory()->create([
            'client_id' => $this->client->id,
            'name' => 'Original Script',
        ]);

        $this->actingAs($this->user)
            ->post(route('scripts.clone', $script))
            ->assertRedirect();

        $this->assertDatabaseHas('scripts', [
            'name' => 'Original Script (Copy)',
            'client_id' => $this->client->id,
            'created_by' => $this->user->id,
            'is_active' => false,
        ]);
    }

    public function test_toggle_status_changes_script_activation()
    {
        $script = Script::factory()->create([
            'client_id' => $this->client->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->postJson(route('scripts.toggle-status', $script))
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'is_active' => false,
            ]);

        $this->assertDatabaseHas('scripts', [
            'id' => $script->id,
            'is_active' => false,
        ]);
    }

    public function test_export_returns_script_data()
    {
        $script = Script::factory()->create([
            'client_id' => $this->client->id,
            'name' => 'Test Script',
            'code' => 'return "Hello, World!";',
        ]);

        $this->actingAs($this->user)
            ->getJson(route('scripts.export', $script))
            ->assertStatus(200)
            ->assertJsonStructure([
                'name',
                'description',
                'code',
                'language',
                'version',
                'configuration',
                'tags',
                'exported_at',
                'exported_by',
            ]);
    }

    public function test_import_creates_script_from_exported_data()
    {
        $scriptData = [
            'name' => 'Imported Script',
            'description' => 'Imported Description',
            'code' => 'return "Imported!";',
            'language' => 'javascript',
            'configuration' => [],
            'tags' => ['imported'],
        ];

        $this->actingAs($this->user)
            ->post(route('scripts.import'), [
                'script_data' => json_encode($scriptData),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('scripts', [
            'name' => 'Imported Script (Imported)',
            'client_id' => $this->client->id,
            'created_by' => $this->user->id,
            'is_active' => false,
        ]);
    }

    public function test_unauthorized_access_is_denied()
    {
        $otherClient = Client::factory()->create();
        $otherUser = User::factory()->create(['client_id' => $otherClient->id]);
        $script = Script::factory()->create([
            'client_id' => $this->client->id,
        ]);

        $this->actingAs($otherUser)
            ->get(route('scripts.show', $script))
            ->assertStatus(403);
    }

    public function test_unauthenticated_access_redirects_to_login()
    {
        $script = Script::factory()->create([
            'client_id' => $this->client->id,
        ]);

        $this->get(route('scripts.show', $script))
            ->assertRedirect(route('login'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}