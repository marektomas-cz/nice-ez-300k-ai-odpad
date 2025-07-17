FROM denoland/deno:alpine-1.39.0

# Create app directory
WORKDIR /app

# Copy the script executors
COPY script_executor.ts .
COPY script_executor_enhanced.ts .

# Cache dependencies
RUN deno cache script_executor_enhanced.ts

# Create non-root user
RUN addgroup -g 1001 -S denouser && \
    adduser -u 1001 -S denouser -G denouser

# Switch to non-root user
USER denouser

# Expose port
EXPOSE 8080

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
  CMD deno eval "fetch('http://localhost:8080/health').then(r => r.ok ? Deno.exit(0) : Deno.exit(1))" || exit 1

# Run the script executor
CMD ["deno", "run", "--allow-net", "--allow-env", "--no-check", "script_executor_enhanced.ts"]