# 寫作風格：模組化開發規範

本專案的業務功能一律以「模組（package）」為單位開發。每個模組自帶路由、設定、migration、語系、事件與測試，透過 Service Provider 掛進主程式；`app/` 只放跨模組共用、與單一業務無關的程式（例如 Inertia 共享資料 middleware）。

> 範例中的 vendor 名稱 `fitness`、命名空間 `Fitness\` 為本專案約定，以下以 `<vendor>` 表示。

---

## 1. 模組目錄結構

```
packages/<vendor>/<module>/
├── composer.json                  # 模組名稱、autoload、依賴
├── README.md                      # 此模組對外提供的 Events / Listeners / 合約
├── src/
│   ├── <Module>ServiceProvider.php
│   ├── helpers.php                # 選用：全域存取函式（例如 manager 捷徑）
│   ├── config/<vendor>/<module>.php
│   ├── routes/web.php
│   ├── lang/zh_TW/*.php
│   ├── Database/
│   │   ├── migrations/
│   │   └── Seeders/
│   ├── Models/
│   ├── Repositories/
│   │   ├── Contracts/<Name>Repository.php   # 介面
│   │   └── <Name>Repository.php             # 實作
│   ├── Services/                  # 商業邏輯、Manager
│   ├── Drivers/                   # 可替換實作（見 §5）
│   │   ├── Contracts/
│   │   └── <Base>.php             # abstract 基底，放預設行為
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Actions/           # 可組合的 controller 行為 trait
│   │   │   └── Traits/
│   │   └── Requests/
│   ├── Events/
│   ├── Listeners/
│   ├── Jobs/
│   ├── Commands/
│   └── Exceptions/
└── tests/
    ├── Unit/
    └── Feature/
```

- 目錄依需要建立，不建立空目錄。
- 目前只有使用者端，Controllers / Requests / Repositories 不分 `Admin/`、`Web/` 子目錄；日後加入後台時，再依使用對象拆成 `Admin/` 與 `Web/`。

## 2. 命名規則

| 項目 | 規則 | 範例 |
|---|---|---|
| 模組目錄 | kebab-case | `food-analyzer`、`food-analyzer-gemini` |
| 命名空間 | `<Vendor>\<Module>`（StudlyCase） | `Fitness\FoodAnalyzer` |
| Service Provider | `<Module>ServiceProvider` | `MealServiceProvider` |
| 設定鍵 | `<vendor>.<module>`（snake_case） | `config('fitness.food_analyzer.daily_limit')` |
| 語系命名空間 | `<Vendor><Module>` | `trans('FitnessMeal::errors.not_found')` |
| 路由名稱 | `<vendor>.<module>.<action>` | `fitness.meal.store` |
| 容器綁定的 driver | `<vendor>.<module>.<driver>` | `fitness.food_analyzer.gemini` |
| 擴充模組（driver） | `<核心模組>-<實作名>` | `food-analyzer-gemini`、`social-login-google` |

## 3. 模組註冊

每新增一個模組，要同步改三個地方：

1. 根目錄 `composer.json` 的 `autoload.psr-4` 加 `"<Vendor>\\<Module>\\": "packages/<vendor>/<module>/src/"`；`autoload-dev.psr-4` 加 `"<Vendor>\\<Module>\\Tests\\": "packages/<vendor>/<module>/tests/"`，然後 `composer dump-autoload`。
2. `bootstrap/providers.php` 加入該模組的 Service Provider。
3. `phpunit.xml` 的 Unit / Feature testsuite 已用萬用路徑 `packages/<vendor>/*/tests/Unit`、`.../Feature` 收錄，不需逐一加入。

## 4. Service Provider

`boot()` 只負責依序呼叫 `setupXxx()`，每個掛載點一個方法，沒用到的就不寫：

```php
class MealServiceProvider extends ServiceProvider
{
    /**
     * 掛載模組的設定、容器綁定、語系、migration、路由與事件。
     */
    public function boot(): void
    {
        $this->setupConfig();
        $this->bindInstances();
        $this->registerSingletons();
        $this->setupLang();
        $this->setupMigrations();
        $this->setupRoutes();
        $this->setupEvents();
    }

    /**
     * 合併模組設定，讓 config('fitness.meal.*') 可用。
     */
    protected function setupConfig(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/fitness/meal.php', 'fitness.meal');
    }

    /**
     * 依設定檔的 binds 綁定 Repository 介面與實作。
     */
    protected function bindInstances(): void
    {
        foreach (config('fitness.meal.binds', []) as $interface => $target) {
            $this->app->bind($interface, $target);
        }
    }

    /**
     * 依設定檔的 singletons 註冊單例服務（例如 Manager）。
     */
    protected function registerSingletons(): void
    {
        foreach (config('fitness.meal.singletons', []) as $abstract => $class) {
            $this->app->singleton($abstract, $class);
        }
    }

    /**
     * 載入模組語系，以 FitnessMeal:: 前綴取用。
     */
    protected function setupLang(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/lang', 'FitnessMeal');
    }

    /**
     * 載入模組自己的 migration。
     */
    protected function setupMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
    }

    /**
     * 載入模組路由。
     */
    protected function setupRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
    }

    /**
     * 註冊此模組監聽的事件（含其他模組發出的事件）。
     */
    protected function setupEvents(): void
    {
        Event::listen(MealConfirmed::class, RefreshSavedFoodsListener::class);
    }
}
```

## 5. 設定檔：binds / singletons 集中管理

Repository 介面與實作的對應、Manager 單例都寫在模組設定檔，不散落在 Provider 裡：

```php
// src/config/fitness/meal.php
return [
    'binds' => [
        \Fitness\Meal\Repositories\Contracts\MealRepository::class => \Fitness\Meal\Repositories\MealRepository::class,
    ],
    'singletons' => [],

    'daily_photo_limit' => env('MEAL_DAILY_PHOTO_LIMIT', 20),
];
```

- 可調整的數值（上限、預設值、外部服務金鑰）一律走 `env()` → 模組設定檔 → `config()`，程式內不寫死。

## 6. Repository 模式

- 資料存取一律透過 Repository；Controller、Service、Listener 不直接寫 Eloquent 查詢。
- 每個 Repository 都有 `Contracts/` 介面，呼叫端只依賴介面，以 `app(介面::class)` 取得實作。
- 跨模組取資料時，只能用對方模組的 Repository 介面或事件，不直接 query 對方的資料表。

```php
interface MealRepository
{
    public function find(int $id): ?Meal;
    public function getByDate(int $userId, CarbonInterface $date): Collection;
}

class MealRepository implements RepositoryInterface
{
    /**
     * 取得使用者某一天（依使用者時區切日）的所有餐點。
     */
    public function getByDate(int $userId, CarbonInterface $date): Collection
    {
        // ...
    }
}
```

## 7. Driver 模式（可替換的實作）

需要「同一件事、多種實作」時（LLM 供應商、第三方登入、日後的金流／通知等），拆成**核心模組 + driver 模組**：

- 核心模組定義：
  - `Drivers/Contracts/<Name>.php`：介面
  - `Drivers/<Name>.php`：abstract 基底，實作共用預設行為；子類別只覆寫需要的方法
  - `Services/<Name>Manager.php`：以別名取得 driver，並快取已建立的實例
  - `helpers.php`：提供捷徑函式
- driver 模組只做一件事：實作 driver，並在 Provider 綁定到容器 `'<vendor>.<module>.<driver>'`。
- 核心模組不知道有哪些 driver；要用哪個由設定決定。

```php
// 核心模組：Services/FoodAnalyzerManager.php
class FoodAnalyzerManager
{
    private array $drivers = [];

    /**
     * 以別名取得食物分析 driver，例如 'gemini'。
     */
    public function get(?string $alias = null): FoodAnalyzer
    {
        $alias ??= config('fitness.food_analyzer.default');

        return $this->drivers[$alias] ??= app('fitness.food_analyzer.'.$alias);
    }
}

// 核心模組：helpers.php
if (! function_exists('food_analyzer')) {
    function food_analyzer(): FoodAnalyzerManager
    {
        return app('fitness.food_analyzer');
    }
}

// driver 模組：GeminiServiceProvider
protected function registerDriver(): void
{
    $this->app->bind('fitness.food_analyzer.gemini', Gemini::class);
}
```

## 8. 事件驅動的跨模組擴充

- 模組之間以事件溝通，**發出事件的模組不依賴監聽者**；新增功能時優先「新增 listener」，而不是修改核心模組。
- 兩種事件用途：
  1. **通知型**：狀態改變後發出（例如 `MealConfirmed`、`WeightLogged`），其他模組各自反應。
  2. **收集型**：核心模組發出事件、讓其他模組往事件物件裡加東西，再取回結果（例如每日分析頁收集各模組的摘要卡片）。

```php
// 收集型事件
class DailySummaryCollecting
{
    private array $sections = [];

    public function __construct(public readonly int $userId, public readonly CarbonInterface $date) {}

    /**
     * 讓各模組加入自己的每日摘要區塊。
     */
    public function addSection(string $key, array $data): self
    {
        $this->sections[$key] = $data;

        return $this;
    }

    /**
     * 取回所有模組加入的摘要區塊。
     */
    public function getSections(): array
    {
        return $this->sections;
    }
}

// 發出端
$event = new DailySummaryCollecting($userId, $date);
Event::dispatch($event);
$sections = $event->getSections();
```

- 監聽器在監聽方模組的 Provider `setupEvents()` 註冊；耗時的 listener 實作 `ShouldQueue`。
- 模組 README 列出本模組發出的 Events 與監聽的 Listeners。

## 9. Controller 與 Request

- Controller 保持精簡：驗證交給 FormRequest，資料交給 Repository，邏輯交給 Service，最後 `Inertia::render()` 或 redirect。
- 可重用的 controller 行為抽成 `Http/Controllers/Actions/HasXxx` trait，用 `use` 組合；需要被不同實作共用的流程，trait 內提供可覆寫的 hook 方法（預設不做事）。
- 錯誤訊息一律走語系檔：`trans('FitnessMeal::errors.xxx')`。

## 10. Model

- 狀態值以常數定義在 Model（`const STATUS_ANALYZING = 0;` 等），不在程式中寫魔術數字。
- 狀態判斷包成方法（`isDraft()`、`canConfirm()`）。
- 有併發風險的狀態轉換，把「允許的來源狀態」寫進 SQL 的 where 條件更新，不能只靠 PHP 端的 if 判斷。
- 類別內以區段註解分隔：GLOBAL VARIABLES / FUNCTIONS / RELATIONS / SCOPES / ACCESSORS / MUTATORS。

## 11. Exception

- 每種失敗原因一個 Exception 類別，放在模組的 `Exceptions/`，類別名稱就說明原因（`AnalyzeFailedException`、`DailyLimitExceededException`）。
- 外部服務失敗（逾時、回傳格式錯誤）要包成模組自己的 Exception，不把第三方例外直接往外丟。

## 12. Migration 放置

- 資料表的 migration 放在擁有該資料表的模組 `src/Database/migrations/`；之後要修改該表，也放在同一個模組。
- 只有跨模組物件（例如跨表 view）或找不到所屬模組時，才放根目錄 `database/migrations/`。

## 13. 測試

- 測試放在模組自己的 `tests/Unit`、`tests/Feature`，命名空間 `<Vendor>\<Module>\Tests\...`，繼承根目錄 `Tests\TestCase`。
- 單元測試用 `new`、`make()` 或 mock，不碰資料庫；外部服務（LLM、Google OAuth）一律 fake，不打真實 API。
- 整合測試需要寫入資料庫時用 `RefreshDatabase` 或交易回滾，不依賴資料庫既有資料。

## 14. 前端（Inertia + Vue）

- 頁面依模組分資料夾：`resources/js/pages/<module>/*.vue`，後端以 `Inertia::render('<module>/Index')` 對應。
- 模組專屬元件放 `resources/js/components/<module>/`；跨模組共用元件放 `resources/js/components/`；shadcn-vue 元件維持在 `components/ui/`。
- 模組的 TS 型別放 `resources/js/types/<module>.ts`。

## 15. 註解與通用規則

- 每個方法上方用一句中文說明用途（做什麼、為什麼），不逐行複述程式碼。
- 類別 docblock 先寫一句「這個類別做什麼、怎麼呼叫」。
- 註解不寫文件或任務編號。
- 只寫解決目前需求的最小範圍，不預先抽象只用一次的程式。
- PHP 格式以 Pint 為準；前端以 Prettier + ESLint 為準。
