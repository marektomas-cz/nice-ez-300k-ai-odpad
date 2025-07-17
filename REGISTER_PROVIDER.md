# Registering ScriptingServiceProvider

## Laravel 11
If using Laravel 11, add the following to your `bootstrap/providers.php`:

```php
return [
    App\Providers\ScriptingServiceProvider::class,
];
```

## Laravel 10 and earlier
If using Laravel 10 or earlier, add the following to the `providers` array in `config/app.php`:

```php
'providers' => [
    // Other providers...
    App\Providers\ScriptingServiceProvider::class,
],
```

## Manual Registration
If the above doesn't work, you can manually register the provider in your `AppServiceProvider` boot method:

```php
public function boot()
{
    $this->app->register(\App\Providers\ScriptingServiceProvider::class);
}
```

## Environment Configuration
Make sure to add these to your `.env` file:

```env
# Deno Configuration
DENO_ENABLED=true
DENO_SERVICE_URL=http://deno-executor:8080
DENO_FALLBACK_TO_V8JS=false
```