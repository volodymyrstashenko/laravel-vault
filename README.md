# thevps/laravel-vault

Пароль-менеджер + довідник Wi-Fi для Laravel + Inertia + Vue.

- **Паролі** — записи (логін/пароль/TOTP-секрет/URL/нотатки/довільні поля/іконка/вкладення),
  зібрані у **групи доступу** з рівнями `view` / `edit` / `manage`. Один пароль може бути в
  кількох групах — діє НАЙВИЩИЙ рівень (union). Клієнтський RFC 6238 TOTP і генератор паролів.
- **Видимість** — `group` (лише учасники) або `public` (бачать усі автентифіковані, лише
  перегляд) — окремо на кожному паролі й на кожній мережі Wi-Fi.
- **Wi-Fi** — SSID/пароль/тип захисту/прихованість/діапазони (2.4/5/6 ГГц), QR-код
  підключення (рахується в браузері), профіль `.xml` для `netsh wlan add profile`, віджет
  швидкого доступу в шапці (`AppWifiQuick.vue`).
- **Без адмінського обходу** — навіть адміністратор не бачить чужих паролів без явного
  членства в групі (принцип Bitwarden/1Password).

Не прив'язаний до застосунку — усе app-специфічне винесено в конфіг чи подію:

| Що | Як |
|---|---|
| Модель користувача | `config('vault.user_model')` |
| Таблиця користувачів (FK у міграціях) | `config('vault.users_table')` (типово `users`) |
| Сповіщення про надання доступу | подія `Thevps\Vault\Events\CredentialGroupAccessGranted` |
| Хто керує публічними Wi-Fi мережами | `config('vault.wifi_manage_gate')` (callable) |
| URL / імена маршрутів | `config('vault.routes')` (`passwords` / `password-groups` / `wifi`) |
| Сторінки Inertia | `config('vault.inertia_page_prefix')` |
| Мультитенантність | `config('vault.institution_resolver')` |
| Список доступних користувачів | `config('vault.available_users_resolver')` |

## Встановлення

```bash
composer require thevps/laravel-vault
php artisan vendor:publish --tag=laravel-vault-config
php artisan vendor:publish --tag=laravel-vault-frontend
php artisan migrate
```

`vendor:publish --tag=laravel-vault-frontend` копіює Vue/TS у:

```
resources/js/pages/passwords/{Index,Show,Create,Edit}.vue
resources/js/pages/password-groups/{Index,Show,Create}.vue
resources/js/pages/wifi/Index.vue
resources/js/components/credentials/{CredentialForm,CredentialIcon,TotpCode}.vue
resources/js/components/wifi/{WifiQrCode,WifiConnectModal,AppWifiQuick}.vue
resources/js/lib/{credentials,wifi,totp,passwordGenerator}.ts
resources/js/types/vault.ts
```

Далі відредагуйте `config/vault.php` (`user_model`, `route_middleware`) і додайте `vault.ts` у
свій barrel типів (`resources/js/types/index.ts`), якщо він є.

## Точки адаптації фронтенду (хост володіє копіями)

- **UI-кіт** `@/components/ui/*` (shadcn-vue на **reka-ui**): `avatar badge button checkbox
  dialog dropdown-menu input label select tabs`.
- `@/layouts/AppLayout.vue` — макет сторінки (breadcrumbs slot).
- `@/components/ConfirmDeleteDialog.vue` — контрольований діалог (`open` + `@confirm`).
- `@/components/InputError.vue`, `@/composables/useInitials`, `@/composables/useTableQuery`,
  `@/components/data-table` (`DataTable`), `@/lib/table` (`fromQueryParams`).
- Тости через Inertia flash (`success`/`error`) — контролер лише робить `->with('success', …)`.
- npm: `qrcode`, `@lucide/vue`, `@inertiajs/vue3`.
- Tailwind-токени: `--success`/`--success-foreground`, `--warning`, `--viz-sky`.
- **`AppWifiQuick.vue`** — хост монтує у свою шапку сам (напр. поруч із перемикачем теми).

## Подія `CredentialGroupAccessGranted`

```php
use Illuminate\Support\Facades\Event;
use Thevps\Vault\Events\CredentialGroupAccessGranted;

Event::listen(CredentialGroupAccessGranted::class, function (CredentialGroupAccessGranted $e) {
    $e->user->notify(new CredentialGroupAccessNotification($e->group, $e->accessLevel));
});
```

## Wi-Fi: керування публічними мережами

`public`-мережу бачать усі; хто може її створити/редагувати/видалити — вирішує:

```php
// config/vault.php
'wifi_manage_gate' => fn ($user) => $user->can('wifi.manage'),
```

`group`-мережі завжди керуються `manage`-учасниками їхньої групи (цей gate не діє).

## Мультитенантність (опційно)

Однотенантний за замовчуванням — `institution_id` присутня в схемі всіх таблиць, але не
використовується, доки хост не задасть резолвер:

```php
'institution_resolver' => fn () => request()->route('institution')?->id,
```

Тоді `Credential` / `CredentialGroup` / `WifiNetwork` фільтрують кожен запит (глобальний scope)
і проставляють `institution_id` новим рядкам. Наявні рядки хост бекфілить сам.

## Перехід із власної копії модуля

Міграції `create_*` мають гард `Schema::hasTable(...)` — на застосунку, який уже мав ці
таблиці, вони no-op. Міграції `add_vault_columns_*` (guard `Schema::hasColumn`) дописують нові
колонки (`visibility`, `institution_id`, `bands`, `location`, `notes`) до наявних таблиць.

## Тести

```bash
composer install
vendor/bin/phpunit
```

Пакетний Testbench-сьют (`tests/VaultFlowTest.php`) не залежить від жодного хост-застосунку.
