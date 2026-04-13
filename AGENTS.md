# AGENTS.md

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
