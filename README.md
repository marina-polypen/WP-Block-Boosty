# WP Block Boosty

**Version:** 1.6.0
**Requires PHP:** 8.0+
**Requires WordPress:** 6.0+

---

## Описание

**WP Block Boosty** — плагин для WordPress, который визуально трансформирует стандартные блоки Gutenberg. Плагин предоставляет единую панель управления для настройки стилей цитат, списков, таблиц, изображений, галерей, заголовков, чередующихся секций и секции **Pros / Cons** на базе `wp-block-columns`.

Плагин виден в списке плагинов, страница настроек доступна из бокового меню WordPress.

---

## Доступ к настройкам

Пункт меню: **Настройки → Block Boosty**. Прямая ссылка:

```
/wp-admin/options-general.php?page=wp-block-boosty-settings
```

Во вкладке **Global** доступны инструменты **Backup & Restore**: экспорт настроек в JSON, импорт из JSON и сброс к значениям по умолчанию.

---

## Поддерживаемые секции

| # | Секция | Описание |
|---|--------|----------|
| 1 | **Blockquotes** | Фото автора, имя, роль, иконка кавычки. Полный перенос из Author for Blockquotes. |
| 2 | **Ordered Lists (ol)** | Кастомные цифровые маркеры, `strong` как заголовок внутри `li`. |
| 3 | **Unordered Lists (ul)** | Карточный дизайн, опциональные декоративные номера (01, 02…). |
| 4 | **Tables** | Zebra-стилизация, цветная шапка, тени, закругления. |
| 5 | **Lead Paragraph** | Автоматическое выделение первого абзаца поста. Поддерживает 3 премиум Hero-шаблона (Aurora, Glass, Editorial) для яркого старта статьи. |
| 6 | **Heading Decor** | Линии, квадраты или Dashicons у заголовков H1-H4. Подсветка слов. |
| 7 | **Images (Figure)** | Border radius, тени, hover-эффекты для всех изображений. |
| 8 | **Gallery → Carousel** | Трансформация стандартной галереи в горизонтальный слайдер (CSS Scroll Snap). |
| 9 | **Latest Posts** | Карточная стилизация с Lift Up / Scale hover-эффектом. |
| 10 | **Alternating Sections** | Автоматическая группировка контента между H2 в odd/even секции. |
| 11 | **Pros / Cons Columns** | Секция на базе `wp-block-columns.col-pros-cons` с отдельными стилями для Pros/Cons, скинами, иконками и декором. |

---

## Глобальные опции

- **Colors** — основной и дополнительный цвета
- **Cascade Primary Color** — если включено, дефолтные цвета секций подменяются Primary Color; выключите, чтобы каждая секция использовала свои дефолты
- **Borders** — border-radius, толщина рамки (normal/hover)
- **Shadows** — тени (normal/hover)
- **CSS Prefix** — префикс для CSS-классов (по умолчанию: `be`)
- **Display Rules** — Pages, Posts, конкретные ID
- **Transition Speed** — скорость анимаций

---

## Pros / Cons Columns

Секция активируется **только** если блок Gutenberg Columns имеет класс:

```html
wp-block-columns col-pros-cons
```

### Как использовать

1. Создай блок `Columns`
2. Добавь класс `col-pros-cons`
3. Внутри каждой колонки можно использовать заголовок как:
   - `p`
   - `h3`
   - `h4`
4. Ниже заголовка используй обычный `ul > li`

### Важно

- Внутри `col-pros-cons` стандартная обработка секции **UL cards** не применяется.
- Это сделано специально, чтобы `ul/li` внутри Pros/Cons рендерились по собственным правилам.

### Скины блока

Можно задать **дефолтный скин** в админке или переопределить его прямо на блоке через дополнительный класс:

- `pc-skin-soft` — мягкие карточки, большая иконка-декор в углу
- `pc-skin-goodbad` — крупный заголовок в капсе + tag справа
- `pc-skin-editorial` — градиентный фон вокруг блока + внутренние карточки
- `pc-skin-minimal` — минимальный стиль без лишнего декора

Пример:

```html
wp-block-columns col-pros-cons pc-skin-editorial
```

Если `pc-skin-*` не задан, используется значение **Default Skin** из настроек.

### Настройки Pros / Cons

Во вкладке **Pros/Cons** доступны:

- отдельный фон для `Pros` и `Cons`
- отдельный фон заголовка для `Pros` и `Cons`
- отдельный цвет иконок списка для `Pros` и `Cons`
- отдельный цвет декора для `Pros` и `Cons`
- выбор иконок списка:
  - `check`, `plus`
  - `cross`, `minus`
- выбор типа декора:
  - Pros: `thumb-up`, `check`, `plus`, `star`, `spark`
  - Cons: `thumb-down`, `cross`, `minus`, `alert`, `ban`
- размер и прозрачность декора
- выбор дефолтного скина

---

## Lead Paragraph / Hero Section

Первый абзац статьи можно превратить в эффектную **Hero Section**. Для этого во вкладке "Lead P" предусмотрен переключатель **Hero Skin (Overrides custom settings)**. Вы можете выбрать один из 3 ярких шаблонов:

- **Aurora** — градиентный mesh с анимированными орбами на тёмном фоне.
- **Glass** — светлый glassmorphism с блюром, свечением и полупрозрачными рамками.
- **Editorial** — премиальный тёмный фон с акцентным свечением.

Если выбран шаблон (не "None"), он **полностью переопределяет** ручные настройки фона, отступов и теней для Lead Paragraph, создавая завершенный премиальный вид.

Определение вводного абзаца устойчиво к структуре: плагин пропускает пустые абзацы и заголовки внутри блоков Pros/Cons и цитат, выбирая реальный первый текстовый абзац.

---

## Структура файлов

```
wp-block-boosty/
├── wp-block-boosty.php          # Точка входа
├── uninstall.php                # Очистка опций/кеша при удалении
├── includes/
│   ├── class-settings.php       # Дефолты, sanitize, get/set
│   ├── class-admin.php          # Админ-панель с вкладками
│   └── class-frontend.php       # Обработка контента, dynamic CSS, Pros/Cons skins
├── assets/
│   ├── css/
│   │   ├── admin.css            # Стили админки
│   │   └── frontend.css         # Базовые фронтенд-стили
│   └── js/
│       ├── admin.js             # Tabs, Color Picker, Media Upload
│       └── frontend.js          # Carousel, Latest Posts, Scroll Animations
```

---

## Технические детали

- **Единый массив опций**: `wp_block_boosty_options` — минимизация запросов к БД
- **Кеширование**: настройки кешируются в памяти на запрос; динамический CSS кешируется в transient (`wpbb_dynamic_css`) и сбрасывается при сохранении
- **Uninstall**: `uninstall.php` удаляет опции и transient при удалении плагина
- **Sanitization**: `sanitize_text_field`, `absint` при сохранении
- **Escaping**: `esc_attr`, `esc_html`, `esc_url` при выводе
- **Nonce**: через WP Settings API (`settings_fields`)
- **Prefix**: все CSS-классы используют настраиваемый префикс (default: `be-`)
- **Динамические стили**: генерируются в `wp_head`, CSS Variables для глобальных токенов
- **Vanilla JS**: без jQuery на фронте, CSS Scroll Snap для карусели
- **IntersectionObserver**: micro-animations при скролле (fade-in с stagger)
- **Pros / Cons**: секция обрабатывается через DOMDocument только для `wp-block-columns.col-pros-cons`
- **Skins**: поддерживаются через CSS-классы `pc-skin-*` на конкретном блоке
