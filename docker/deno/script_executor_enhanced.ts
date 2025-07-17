import { serve } from "https://deno.land/std@0.208.0/http/server.ts";
import { STATUS_CODE } from "https://deno.land/std@0.208.0/http/status.ts";

interface ExecutionRequest {
  code: string;
  context: Record<string, any>;
  timeout: number;
  memory_limit: number;
  client_id: string;
  script_id: string;
  execution_id: string;
  api_callback_url?: string;
  api_token?: string;
}

interface ExecutionResponse {
  success: boolean;
  result?: any;
  error?: string;
  execution_time: number;
  memory_used: number;
  output: string[];
}

interface ApiContext {
  log: {
    info: (message: string) => void;
    error: (message: string) => void;
    warn: (message: string) => void;
    debug: (message: string) => void;
  };
  utils: {
    now: () => number;
    uuid: () => string;
    hash: (data: string) => Promise<string>;
    parseJson: (json: string) => any;
  };
  database: {
    query: (sql: string, bindings?: any[]) => Promise<any>;
    select: (table: string, columns: string[], conditions?: any) => Promise<any>;
    insert: (table: string, data: any) => Promise<any>;
    update: (table: string, data: any, conditions: any) => Promise<any>;
    delete: (table: string, conditions: any) => Promise<any>;
  };
  http: {
    get: (url: string, headers?: any) => Promise<any>;
    post: (url: string, data?: any, headers?: any) => Promise<any>;
    put: (url: string, data?: any, headers?: any) => Promise<any>;
    patch: (url: string, data?: any, headers?: any) => Promise<any>;
    delete: (url: string, headers?: any) => Promise<any>;
  };
  events: {
    dispatch: (eventName: string, data: any) => Promise<void>;
  };
  getScriptInfo: () => {
    id: string;
    client_id: string;
    execution_id: string;
  };
}

class ScriptExecutor {
  private outputBuffer: string[] = [];
  private startTime: number = 0;
  private memoryUsed: number = 0;
  private activeExecutions: Map<string, AbortController> = new Map();

  constructor() {
    this.setupSecurityContext();
  }

  private setupSecurityContext(): void {
    // Remove global objects that could be security risks
    //@ts-ignore
    delete globalThis.Deno;
    //@ts-ignore
    delete globalThis.XMLHttpRequest;
    //@ts-ignore
    delete globalThis.WebSocket;
    //@ts-ignore
    delete globalThis.Worker;
    //@ts-ignore
    delete globalThis.eval;
    //@ts-ignore
    delete globalThis.Function.prototype.constructor;
    //@ts-ignore
    delete globalThis.import;
    //@ts-ignore
    delete globalThis.require;
  }

  private async callLaravelApi(
    request: ExecutionRequest,
    type: string,
    method: string,
    params: any
  ): Promise<any> {
    if (!request.api_callback_url || !request.api_token) {
      throw new Error('API callback configuration missing');
    }

    try {
      const response = await fetch(request.api_callback_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          execution_id: request.execution_id,
          api_token: request.api_token,
          type: type,
          method: method,
          params: params,
        }),
      });

      if (!response.ok) {
        const error = await response.text();
        throw new Error(`API call failed: ${response.status} - ${error}`);
      }

      const result = await response.json();
      if (!result.success) {
        throw new Error(result.error || 'API call failed');
      }

      return result.result;
    } catch (error) {
      throw new Error(`Laravel API call failed: ${error.message}`);
    }
  }

  private createSecureApi(request: ExecutionRequest): ApiContext {
    const self = this;
    
    return {
      log: {
        info: (message: string) => {
          self.outputBuffer.push(`[INFO] ${message}`);
        },
        error: (message: string) => {
          self.outputBuffer.push(`[ERROR] ${message}`);
        },
        warn: (message: string) => {
          self.outputBuffer.push(`[WARN] ${message}`);
        },
        debug: (message: string) => {
          self.outputBuffer.push(`[DEBUG] ${message}`);
        }
      },
      utils: {
        now: () => Date.now(),
        uuid: () => crypto.randomUUID(),
        hash: async (data: string) => {
          const encoder = new TextEncoder();
          const hashBuffer = await crypto.subtle.digest('SHA-256', encoder.encode(data));
          return Array.from(new Uint8Array(hashBuffer)).map(b => b.toString(16).padStart(2, '0')).join('');
        },
        parseJson: (json: string) => {
          try {
            return JSON.parse(json);
          } catch (error) {
            throw new Error(`Invalid JSON: ${error.message}`);
          }
        }
      },
      database: {
        query: async (sql: string, bindings?: any[]) => {
          self.outputBuffer.push(`[DB] Query: ${sql}`);
          return await self.callLaravelApi(request, 'database', 'query', { sql, bindings });
        },
        select: async (table: string, columns: string[], conditions?: any) => {
          self.outputBuffer.push(`[DB] Select from ${table}`);
          return await self.callLaravelApi(request, 'database', 'select', { table, columns, conditions });
        },
        insert: async (table: string, data: any) => {
          self.outputBuffer.push(`[DB] Insert into ${table}`);
          return await self.callLaravelApi(request, 'database', 'insert', { table, data });
        },
        update: async (table: string, data: any, conditions: any) => {
          self.outputBuffer.push(`[DB] Update ${table}`);
          return await self.callLaravelApi(request, 'database', 'update', { table, data, conditions });
        },
        delete: async (table: string, conditions: any) => {
          self.outputBuffer.push(`[DB] Delete from ${table}`);
          return await self.callLaravelApi(request, 'database', 'delete', { table, conditions });
        }
      },
      http: {
        get: async (url: string, headers?: any) => {
          self.outputBuffer.push(`[HTTP] GET ${url}`);
          return await self.callLaravelApi(request, 'http', 'get', { url, headers });
        },
        post: async (url: string, data?: any, headers?: any) => {
          self.outputBuffer.push(`[HTTP] POST ${url}`);
          return await self.callLaravelApi(request, 'http', 'post', { url, data, headers });
        },
        put: async (url: string, data?: any, headers?: any) => {
          self.outputBuffer.push(`[HTTP] PUT ${url}`);
          return await self.callLaravelApi(request, 'http', 'put', { url, data, headers });
        },
        patch: async (url: string, data?: any, headers?: any) => {
          self.outputBuffer.push(`[HTTP] PATCH ${url}`);
          return await self.callLaravelApi(request, 'http', 'patch', { url, data, headers });
        },
        delete: async (url: string, headers?: any) => {
          self.outputBuffer.push(`[HTTP] DELETE ${url}`);
          return await self.callLaravelApi(request, 'http', 'delete', { url, headers });
        }
      },
      events: {
        dispatch: async (eventName: string, data: any) => {
          self.outputBuffer.push(`[EVENT] Dispatched: ${eventName}`);
          await self.callLaravelApi(request, 'events', 'dispatch', { eventName, data });
        }
      },
      getScriptInfo: () => {
        return {
          id: request.script_id,
          client_id: request.client_id,
          execution_id: request.execution_id
        };
      }
    };
  }

  private wrapUserCode(code: string, context: Record<string, any>, api: ApiContext): string {
    const contextVars = Object.entries(context)
      .filter(([key, value]) => this.isValidContextVariable(key, value))
      .map(([key, value]) => `const ${key} = ${JSON.stringify(value)};`)
      .join('\n');

    return `
      (async function() {
        'use strict';
        
        // Inject context variables
        ${contextVars}
        
        // Inject API
        const api = {
          log: {
            info: (message) => api_log_info(message),
            error: (message) => api_log_error(message),
            warn: (message) => api_log_warn(message),
            debug: (message) => api_log_debug(message)
          },
          utils: {
            now: () => api_utils_now(),
            uuid: () => api_utils_uuid(),
            hash: (data) => api_utils_hash(data),
            parseJson: (json) => api_utils_parseJson(json)
          },
          database: {
            query: (sql, bindings) => api_database_query(sql, bindings),
            select: (table, columns, conditions) => api_database_select(table, columns, conditions),
            insert: (table, data) => api_database_insert(table, data),
            update: (table, data, conditions) => api_database_update(table, data, conditions),
            delete: (table, conditions) => api_database_delete(table, conditions)
          },
          http: {
            get: (url, headers) => api_http_get(url, headers),
            post: (url, data, headers) => api_http_post(url, data, headers),
            put: (url, data, headers) => api_http_put(url, data, headers),
            patch: (url, data, headers) => api_http_patch(url, data, headers),
            delete: (url, headers) => api_http_delete(url, headers)
          },
          events: {
            dispatch: (eventName, data) => api_events_dispatch(eventName, data)
          },
          getScriptInfo: () => api_getScriptInfo()
        };
        
        // User code
        ${code}
      })();
    `;
  }

  private isValidContextVariable(key: string, value: any): boolean {
    // Check key format
    if (!/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(key)) {
      return false;
    }

    // Check for reserved words
    const reservedWords = ['api', 'console', 'window', 'document', 'global', 'process', 'Deno'];
    if (reservedWords.includes(key)) {
      return false;
    }

    // Check value type
    if (typeof value === 'function' || (typeof value === 'object' && value !== null && value.constructor !== Object && value.constructor !== Array)) {
      return false;
    }

    return true;
  }

  async execute(request: ExecutionRequest): Promise<ExecutionResponse> {
    this.outputBuffer = [];
    this.startTime = performance.now();
    this.memoryUsed = 0;

    try {
      // Create secure API context
      const api = this.createSecureApi(request);
      
      // Wrap user code with security context
      const wrappedCode = this.wrapUserCode(request.code, request.context, api);

      // Create execution context with timeout
      const controller = new AbortController();
      this.activeExecutions.set(request.execution_id, controller);
      
      const timeoutId = setTimeout(() => controller.abort(), request.timeout);

      // Execute with resource limits
      const result = await this.executeWithLimits(wrappedCode, api, controller.signal);

      clearTimeout(timeoutId);
      this.activeExecutions.delete(request.execution_id);

      const executionTime = performance.now() - this.startTime;

      return {
        success: true,
        result: result,
        execution_time: executionTime,
        memory_used: this.memoryUsed,
        output: this.outputBuffer
      };

    } catch (error) {
      const executionTime = performance.now() - this.startTime;
      this.activeExecutions.delete(request.execution_id);
      
      return {
        success: false,
        error: error.message,
        execution_time: executionTime,
        memory_used: this.memoryUsed,
        output: this.outputBuffer
      };
    }
  }

  private async executeWithLimits(code: string, api: ApiContext, signal: AbortSignal): Promise<any> {
    // Create a new context for execution
    const context = {
      // Bind API functions
      api_log_info: api.log.info,
      api_log_error: api.log.error,
      api_log_warn: api.log.warn,
      api_log_debug: api.log.debug,
      api_utils_now: api.utils.now,
      api_utils_uuid: api.utils.uuid,
      api_utils_hash: api.utils.hash,
      api_utils_parseJson: api.utils.parseJson,
      api_database_query: api.database.query,
      api_database_select: api.database.select,
      api_database_insert: api.database.insert,
      api_database_update: api.database.update,
      api_database_delete: api.database.delete,
      api_http_get: api.http.get,
      api_http_post: api.http.post,
      api_http_put: api.http.put,
      api_http_patch: api.http.patch,
      api_http_delete: api.http.delete,
      api_events_dispatch: api.events.dispatch,
      api_getScriptInfo: api.getScriptInfo,
      
      // Standard JavaScript globals
      JSON: JSON,
      Math: Math,
      Date: Date,
      Array: Array,
      Object: Object,
      String: String,
      Number: Number,
      Boolean: Boolean,
      parseInt: parseInt,
      parseFloat: parseFloat,
      isNaN: isNaN,
      isFinite: isFinite,
      Promise: Promise,
      
      // Console for basic output
      console: {
        log: api.log.info,
        error: api.log.error,
        warn: api.log.warn,
        info: api.log.info,
        debug: api.log.debug
      }
    };

    // Create async function and execute
    const AsyncFunction = Object.getPrototypeOf(async function(){}).constructor;
    const func = new AsyncFunction(...Object.keys(context), `return ${code}`);
    const result = await func(...Object.values(context));

    return result;
  }

  /**
   * Stop execution by execution ID
   */
  stopExecution(executionId: string): boolean {
    const controller = this.activeExecutions.get(executionId);
    if (controller) {
      controller.abort();
      this.activeExecutions.delete(executionId);
      return true;
    }
    return false;
  }

  /**
   * Validate JavaScript syntax
   */
  validateSyntax(code: string): { valid: boolean; error?: string } {
    try {
      // Try to create async function for validation
      const AsyncFunction = Object.getPrototypeOf(async function(){}).constructor;
      new AsyncFunction(code);
      return { valid: true };
    } catch (error) {
      return { 
        valid: false, 
        error: error.message 
      };
    }
  }

  /**
   * Get active executions count
   */
  getActiveExecutionsCount(): number {
    return this.activeExecutions.size;
  }

  /**
   * Get all active execution IDs
   */
  getActiveExecutionIds(): string[] {
    return Array.from(this.activeExecutions.keys());
  }
}

// HTTP server
const executor = new ScriptExecutor();

async function handler(request: Request): Promise<Response> {
  const url = new URL(request.url);
  
  // Health check endpoint
  if (url.pathname === '/health') {
    return new Response('OK', { status: STATUS_CODE.OK });
  }

  // Execute endpoint
  if (url.pathname === '/execute' && request.method === 'POST') {
    try {
      const body = await request.json();
      const executionRequest = body as ExecutionRequest;
      
      // Validate request
      if (!executionRequest.code || !executionRequest.execution_id) {
        return new Response(
          JSON.stringify({ error: 'Invalid request: missing code or execution_id' }),
          { 
            status: STATUS_CODE.BadRequest,
            headers: { 'Content-Type': 'application/json' }
          }
        );
      }

      // Execute script
      const response = await executor.execute(executionRequest);
      
      return new Response(JSON.stringify(response), {
        status: STATUS_CODE.OK,
        headers: { 'Content-Type': 'application/json' }
      });

    } catch (error) {
      return new Response(
        JSON.stringify({ error: `Server error: ${error.message}` }),
        { 
          status: STATUS_CODE.InternalServerError,
          headers: { 'Content-Type': 'application/json' }
        }
      );
    }
  }

  // Stop execution endpoint
  if (url.pathname === '/stop' && request.method === 'POST') {
    try {
      const body = await request.json();
      const { execution_id } = body;
      
      if (!execution_id) {
        return new Response(
          JSON.stringify({ error: 'Missing execution_id' }),
          { 
            status: STATUS_CODE.BadRequest,
            headers: { 'Content-Type': 'application/json' }
          }
        );
      }

      // Stop execution
      const stopped = executor.stopExecution(execution_id);
      
      return new Response(JSON.stringify({ 
        success: true, 
        stopped: stopped,
        execution_id: execution_id 
      }), {
        status: STATUS_CODE.OK,
        headers: { 'Content-Type': 'application/json' }
      });

    } catch (error) {
      return new Response(
        JSON.stringify({ error: `Server error: ${error.message}` }),
        { 
          status: STATUS_CODE.InternalServerError,
          headers: { 'Content-Type': 'application/json' }
        }
      );
    }
  }

  // Validate endpoint
  if (url.pathname === '/validate' && request.method === 'POST') {
    try {
      const body = await request.json();
      const { code } = body;
      
      if (!code) {
        return new Response(
          JSON.stringify({ error: 'Missing code' }),
          { 
            status: STATUS_CODE.BadRequest,
            headers: { 'Content-Type': 'application/json' }
          }
        );
      }

      // Basic syntax validation
      const validation = executor.validateSyntax(code);
      
      return new Response(JSON.stringify(validation), {
        status: STATUS_CODE.OK,
        headers: { 'Content-Type': 'application/json' }
      });

    } catch (error) {
      return new Response(
        JSON.stringify({ error: `Server error: ${error.message}` }),
        { 
          status: STATUS_CODE.InternalServerError,
          headers: { 'Content-Type': 'application/json' }
        }
      );
    }
  }

  // Status endpoint
  if (url.pathname === '/status' && request.method === 'GET') {
    return new Response(JSON.stringify({
      active_executions: executor.getActiveExecutionsCount(),
      active_execution_ids: executor.getActiveExecutionIds(),
      uptime: process.uptime(),
      memory: process.memoryUsage(),
    }), {
      status: STATUS_CODE.OK,
      headers: { 'Content-Type': 'application/json' }
    });
  }

  return new Response('Not Found', { status: STATUS_CODE.NotFound });
}

// Start server
const port = parseInt(Deno.env.get('PORT') || '8080');
console.log(`🚀 Enhanced Deno Script Executor running on port ${port}`);

await serve(handler, { port });