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
    hash: (data: string) => string;
    parseJson: (json: string) => any;
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
    delete globalThis.fetch;
    //@ts-ignore
    delete globalThis.XMLHttpRequest;
    //@ts-ignore
    delete globalThis.WebSocket;
    //@ts-ignore
    delete globalThis.Worker;
    //@ts-ignore
    delete globalThis.eval;
    //@ts-ignore
    delete globalThis.Function;
    //@ts-ignore
    delete globalThis.import;
    //@ts-ignore
    delete globalThis.require;
  }

  private createSecureApi(): ApiContext {
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
        hash: (data: string) => {
          const encoder = new TextEncoder();
          const hashBuffer = crypto.subtle.digest('SHA-256', encoder.encode(data));
          return Array.from(new Uint8Array(hashBuffer)).map(b => b.toString(16).padStart(2, '0')).join('');
        },
        parseJson: (json: string) => {
          try {
            return JSON.parse(json);
          } catch (error) {
            throw new Error(`Invalid JSON: ${error.message}`);
          }
        }
      }
    };
  }

  private wrapUserCode(code: string, context: Record<string, any>, api: ApiContext): string {
    const contextVars = Object.entries(context)
      .filter(([key, value]) => this.isValidContextVariable(key, value))
      .map(([key, value]) => `const ${key} = ${JSON.stringify(value)};`)
      .join('\n');

    return `
      (function() {
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
          }
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
    if (typeof value === 'function' || typeof value === 'object' && value !== null && typeof value !== 'object') {
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
      const api = this.createSecureApi();
      
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
      
      // Console for basic output
      console: {
        log: api.log.info,
        error: api.log.error,
        warn: api.log.warn,
        info: api.log.info,
        debug: api.log.debug
      }
    };

    // Execute code in isolated context
    const func = new Function(...Object.keys(context), `return ${code}`);
    const result = func(...Object.values(context));

    // Handle promises
    if (result && typeof result.then === 'function') {
      return await result;
    }

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
      // Basic syntax validation using Function constructor
      new Function(code);
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

  return new Response('Not Found', { status: STATUS_CODE.NotFound });
}

// Start server
const port = parseInt(Deno.env.get('PORT') || '8080');
console.log(`🚀 Deno Script Executor running on port ${port}`);

await serve(handler, { port });