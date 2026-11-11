# AGENTS.md

## Active development workflow

The active directory is `F:\was-pro`, and routine development uses local `main`.
Follow [docs/development-workflow.md](docs/development-workflow.md) for preservation,
local application verification, focused commits, direct push, and exact-commit CI.
Do not create worktrees, preview applications, or PRs by default. Use a separate
branch or PR only for substantial/risky work or an explicit owner request.


Guidance for agentic coding agents working in this repository.

## Project Overview

Multi-tenant waste management platform built with Laravel 12, Inertia.js v2, Vue 3, TypeScript, Tailwind CSS v4, and PostgreSQL schema-based tenancy. Manages waste records, transportation, and FABA (Fly Ash/Bottom Ash) production/utilization with permission-based access control. UI text is in Bahasa Indonesia.

## Build / Lint / Test Commands

### Development

```bash
composer run dev              # Start server + queue + logs + Vite (parallel)
npm run dev                   # Vite dev server only
npm run build                 # Production build
npm run build:ssr             # Production build with SSR
```

### PHP Linting & Formatting

```bash
composer run lint             # pint --parallel (auto-fix)
composer run lint:check       # pint --parallel --test (check only)
vendor/bin/pint --dirty --format agent  # Fix only modified files (run before committing)
```

### Frontend Linting & Formatting

```bash
npm run lint                  # ESLint --fix
npm run lint:check            # ESLint check only
npm run format                # Prettier format resources/
npm run format:check          # Prettier check
npm run types:check           # vue-tsc --noEmit
```

### Testing

```bash
php artisan test --compact                              # Run all tests
php artisan test --compact --filter=test_name           # Run single test by name
php artisan test --compact tests/Feature/DashboardTest.php  # Run specific file
./vendor/bin/pest --compact                             # Pest directly
composer run ci:check                                   # Full CI: lint + format + types + test
```

Test database is `was_pro_test` (pgsql). Never run destructive commands against `was_pro`.

### Before Committing

1. `vendor/bin/pint --dirty --format agent` — fix PHP style
2. `npm run lint && npm run format` — fix frontend style
3. Write/update tests, run affected tests
4. `npm run types:check` — verify TypeScript

## Code Style

### PHP (Pint: `laravel` preset)

- **Indentation**: 4 spaces, UTF-8, LF line endings
- **Curly braces**: Always required, even for single-line control structures
- **Return types**: Explicit on every method. Use `: void`, `: bool`, `: Response`, `: RedirectResponse`, etc.
- **Parameter types**: Always declare. Use nullable `?string $path = null` when needed.
- **Constructors**: Use PHP 8 constructor property promotion. No empty `__construct()` with zero params.
- **Casts**: Use `casts()` method on models, not `$casts` property.
- **Comments**: Prefer PHPDoc blocks. No inline comments unless logic is exceptionally complex.

```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    return $this->paths->contains($path);
}
```

### PHP Naming

- **Models**: PascalCase singular (`WasteRecord`, `FabaMovement`)
- **Controllers**: PascalCase, grouped by domain (`WasteManagement/WasteRecordsController`)
- **Methods**: camelCase (`submitForApproval`, `getStatusLabel`)
- **Scopes**: `scope` prefix (`scopeActive`, `scopePendingApproval`, `scopeForPeriod`)
- **Constants**: UPPER_SNAKE_CASE (`DEFAULT_UNIT`, `MATERIAL_FLY_ASH`)
- **Database columns**: snake_case (`waste_type_id`, `submitted_by`)

### PHP Imports

Group by framework/vendor, alphabetical within groups:

```php
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WasteRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
```

### Validation

- Use Form Request classes (`php artisan make:request`), never inline validation
- Array-based validation rules (not pipe strings) in most form requests
- Include `messages()` method with Indonesian-language error messages
- Shared validation traits in `app/Concerns/` (e.g., `PasswordValidationRules`, `WasteValidationRules`)

```php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'status' => ['required', Rule::in(['draft', 'pending', 'approved'])],
    ];
}

public function messages(): array
{
    return [
        'name.required' => 'Nama wajib diisi.',
    ];
}
```

### Database / Eloquent

- Prefer Eloquent relationships over raw queries. Avoid `DB::`.
- Eager load to prevent N+1: `$query->with('relation')->get()`
- Migrations: anonymous classes, UUID primary keys, `softDeletes()` common
- Tenant migrations go in `database/migrations/tenant/`; public in `database/migrations/public/`
- Foreign keys: `onDelete('restrict')` by default

### Testing (Pest 4)

- Pure Pest syntax — `test('description', function () { ... })`, never PHPUnit-style methods
- Assertions via `expect()`: `expect($result)->toBeTrue()`, `expect($count)->toBe(5)`
- HTTP tests: `$this->actingAs($user)->get('/dashboard')`
- Inertia assertions: `->assertInertia(fn (AssertableInertia $page) => $page->component('Dashboard'))`
- Factories: `User::factory()->create()`, `WasteRecord::factory()->approved()->create()`
- Use `fake()` (global helper), not `$this->faker`
- Create tests via: `php artisan make:test --pest FeatureName`

```php
test('user can view dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Dashboard'));
});
```

### Vue / TypeScript

- **Script setup**: Always `<script setup lang="ts">`
- **Props**: Typed via `defineProps<Props>()` with interface
- **Type imports**: ESLint enforces separate `import type { X }` from value imports
- **Imports order**: builtin → external → internal, alphabetized within groups
- **Path alias**: `@/*` maps to `./resources/js/*`

```vue
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';
import AppLayout from '@/layouts/AppLayout.vue';

interface Props {
    breadcrumbs: BreadcrumbItem[];
}

const props = defineProps<Props>();
</script>
```

### Frontend Naming & Structure

- **Pages**: PascalCase in kebab-case dirs (`waste-management/records/Index.vue`)
- **Components**: PascalCase (`CompactStat.vue`, `DeleteUser.vue`)
- **Composables**: `use` prefix (`useAppearance.ts`, `usePermissions.ts`)
- **UI components** (shadcn): lowercase barrel exports (`Button`, `Input`, `Card`)
- **Icons**: `lucide-vue-next` exclusively

### Forms (Inertia v2 + Wayfinder)

```vue
<Form v-bind="ProfileController.update.form()" v-slot="{ errors, processing }">
    <!-- fields -->
</Form>
```

Import controller actions: `import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController'`

### Styling (Tailwind CSS v4)

- Utility-first, no custom CSS components
- Dark mode via `dark:` variant
- `cn()` utility (clsx + tailwind-merge) for conditional classes
- Design tokens in `resources/js/css/design-tokens.css`
- Custom shadows/gradients: `shadow-[0_24px_60px_-36px_rgba(15,23,42,0.35)]`, `bg-linear-to-br`

## Key Architecture

- **Middleware**: Configured in `bootstrap/app.php` (Laravel 12 streamlined structure)
- **Routes**: `routes/web.php`, `routes/settings.php`, `routes/waste-management.php`, `routes/api.php` (versioned `/v1/`)
- **Wayfinder**: Auto-generates TypeScript route functions in `resources/js/actions/` and `resources/js/routes/`
- **Auth**: Laravel Fortify (headless backend), custom permission/role middleware
- **Tenancy**: PostgreSQL schema-based — `SetTenantSchema` middleware, tenant vs public migrations
- **Navigation**: Centralized in `resources/js/lib/app-navigation.ts` with permission filtering
- **Config**: Never use `env()` outside config files; always use `config('key')`

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.25
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v2
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/wayfinder (WAYFINDER) - v0
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/vue3 (INERTIA_VUE) - v2
- tailwindcss (TAILWINDCSS) - v4
- vue (VUE) - v3
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `wayfinder-development` — Use this skill for Laravel Wayfinder which auto-generates typed functions for Laravel controllers and routes. ALWAYS use this skill when frontend code needs to call backend routes or controller actions. Trigger when: connecting any React/Vue/Svelte/Inertia frontend to Laravel controllers, routes, building end-to-end features with both frontend and backend, wiring up forms or links to backend endpoints, fixing route-related TypeScript errors, importing from @/actions or @/routes, or running wayfinder:generate. Use Wayfinder route functions instead of hardcoded URLs. Covers: wayfinder() vite plugin, .url()/.get()/.post()/.form(), query params, route model binding, tree-shaking. Do not use for backend-only task
- `pest-testing` — Tests applications using the Pest 4 PHP framework. Activates when writing tests, creating unit or feature tests, adding assertions, testing Livewire components, browser testing, debugging test failures, working with datasets or mocking; or when the user mentions test, spec, TDD, expects, assertion, coverage, or needs to verify functionality works.
- `inertia-vue-development` — Develops Inertia.js v2 Vue client-side applications. Activates when creating Vue pages, forms, or navigation; using &lt;Link&gt;, &lt;Form&gt;, useForm, or router; working with deferred props, prefetching, or polling; or when user mentions Vue with Inertia, Vue pages, Vue forms, or Vue navigation.
- `tailwindcss-development` — Styles applications using Tailwind CSS v4 utilities. Activates when adding styles, restyling components, working with gradients, spacing, layout, flex, grid, responsive design, dark mode, colors, typography, or borders; or when the user mentions CSS, styling, classes, Tailwind, restyle, hero section, cards, buttons, or any visual/UI changes.
- `developing-with-fortify` — Laravel Fortify headless authentication backend development. Activate when implementing authentication features including login, registration, password reset, email verification, two-factor authentication (2FA/TOTP), profile updates, headless auth, authentication scaffolding, or auth guards in Laravel applications.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.
- Use the `database-schema` tool to inspect table structure before writing migrations or models.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - `public function __construct(public GitHub $github) { }`
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<!-- Explicit Return Types and Method Params -->
```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
```

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.
- CRITICAL: ALWAYS use `search-docs` tool for version-specific Pest documentation and updated code examples.
- IMPORTANT: Activate `pest-testing` every time you're working with a Pest or testing-related task.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

=== tailwindcss/core rules ===

# Tailwind CSS

- Always use existing Tailwind conventions; check project patterns before adding new ones.
- IMPORTANT: Always use `search-docs` tool for version-specific Tailwind CSS documentation and updated code examples. Never rely on training data.
- IMPORTANT: Activate `tailwindcss-development` every time you're working with a Tailwind CSS or styling-related task.

=== laravel/fortify rules ===

# Laravel Fortify

- Fortify is a headless authentication backend that provides authentication routes and controllers for Laravel applications.
- IMPORTANT: Always use the `search-docs` tool for detailed Laravel Fortify patterns and documentation.
- IMPORTANT: Activate `developing-with-fortify` skill when working with Fortify authentication features.

</laravel-boost-guidelines>
