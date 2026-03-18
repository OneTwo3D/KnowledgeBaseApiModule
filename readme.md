# Knowledge Base API Module for FreeScout

This module (forked from EcomGraduates and @jtorvald) adds a public REST API for the [FreeScout](https://freescout.net) knowledge base with nested category support, analytics, custom URL templates, and a built-in testing interface.

## Requirements

- [FreeScout](https://freescout.net) installed
- FreeScout [Knowledge Base module](https://freescout.net/module/knowledge-base/)

## Installation

1. Download the latest module zip file via the releases card on the right.
2. Transfer the zip file to the server in the `Modules` folder of FreeScout.
3. Unpack the zip file and remove the zip.
4. Activate the module via the Modules page in FreeScout.
5. Go to **Settings → Knowledge Base API** and enter or generate a token, then save.
6. Run migrations: `php artisan module:migrate KnowledgeBaseApiModule`

## Update Instructions

1. Download the latest zip file.
2. Transfer it to the `Modules` folder on your server.
3. Delete the existing `KnowledgeBaseApiModule` folder.
4. Unpack the new zip and remove the zip file.

---

## Features

- **Nested categories** — flat list with `parent_id` by default; pass `?nested=true` for a recursive tree
- **Token authentication** — all endpoints protected by a configurable API token
- **Custom URL templates** — control how `url` and `client_url` are built in every response
- **Multi-locale support** — `?locale=` / `?lang=` parameters on all endpoints
- **Analytics dashboard** — view, category, and search tracking with Chart.js visualisations
- **Built-in test interface** — try every endpoint directly from the settings page

---

## API Endpoints

All endpoints require `?token=YOUR_TOKEN`.

### List all categories

```
GET /api/knowledgebase/{mailboxId}/categories
```

Returns a **flat list** of all visible categories. Each item includes a `parent_id` field (`null` for root categories) so you can build the tree client-side.

**Response:**
```json
{
  "mailbox_id": 1,
  "name": "My Mailbox",
  "categories": [
    {
      "id": 3,
      "parent_id": null,
      "name": "Getting Started",
      "description": "...",
      "url": "https://example.com/kb/category/3",
      "client_url": null,
      "article_count": 4
    },
    {
      "id": 7,
      "parent_id": 3,
      "name": "Installation",
      "description": "...",
      "url": "https://example.com/kb/category/7",
      "client_url": null,
      "article_count": 2
    }
  ]
}
```

Pass `?nested=true` to receive a recursive **tree** instead:

```
GET /api/knowledgebase/{mailboxId}/categories?nested=true
```

```json
{
  "mailbox_id": 1,
  "name": "My Mailbox",
  "categories": [
    {
      "id": 3,
      "name": "Getting Started",
      "description": "...",
      "url": "https://example.com/kb/category/3",
      "client_url": null,
      "article_count": 4,
      "children": [
        {
          "id": 7,
          "name": "Installation",
          "description": "...",
          "url": "https://example.com/kb/category/7",
          "client_url": null,
          "article_count": 2,
          "children": []
        }
      ]
    }
  ]
}
```

---

### Get a category with its articles and subcategories

```
GET /api/knowledgebase/{mailboxId}/categories/{categoryId}
```

Returns the category, its direct **subcategories**, and all its published articles. Tracks a category view for analytics.

**Response:**
```json
{
  "mailbox_id": 1,
  "name": "My Mailbox",
  "category": {
    "id": 3,
    "name": "Getting Started",
    "description": "...",
    "url": "https://example.com/kb/category/3",
    "client_url": null,
    "subcategories": [
      {
        "id": 7,
        "name": "Installation",
        "description": "...",
        "url": "https://example.com/kb/category/7",
        "client_url": null,
        "article_count": 2
      }
    ]
  },
  "articles": [
    {
      "id": 12,
      "title": "Quick start guide",
      "text": "...",
      "url": "https://example.com/kb/article/12",
      "client_url": null
    }
  ]
}
```

---

### Get a specific article

```
GET /api/knowledgebase/{mailboxId}/categories/{categoryId}/articles/{articleId}
```

Returns a single published article. Tracks an article view for analytics.

**Response:**
```json
{
  "mailbox_id": 1,
  "mailbox_name": "My Mailbox",
  "category": {
    "id": 3,
    "name": "Getting Started",
    "url": "https://example.com/kb/category/3",
    "client_url": null
  },
  "article": {
    "id": 12,
    "title": "Quick start guide",
    "text": "...",
    "url": "https://example.com/kb/article/12",
    "client_url": null
  }
}
```

---

### Search articles

```
GET /api/knowledgebase/{mailboxId}/search?q=keyword
```

Case-insensitive full-text search across published article titles and body text. Tracks the search query for analytics.

**Response:**
```json
{
  "mailbox_id": 1,
  "keyword": "installation",
  "count": 2,
  "results": [
    {
      "id": 12,
      "title": "Quick start guide",
      "text": "...",
      "categories": [
        { "id": 3, "name": "Getting Started" }
      ],
      "url": "https://example.com/kb/article/12",
      "client_url": null
    }
  ]
}
```

---

### Get popular content

```
GET /api/knowledgebase/{mailboxId}/popular
```

Returns most-viewed categories and/or articles ranked by view count.

**Response:**
```json
{
  "mailbox_id": 1,
  "name": "My Mailbox",
  "popular_categories": [
    {
      "id": 3,
      "name": "Getting Started",
      "description": "...",
      "view_count": 142,
      "url": "...",
      "client_url": null
    }
  ],
  "popular_articles": [
    {
      "id": 12,
      "title": "Quick start guide",
      "view_count": 87,
      "url": "...",
      "client_url": null,
      "category": { "id": 3, "name": "Getting Started" }
    }
  ]
}
```

---

### Export all KB content

```
GET /api/knowledgebase/{mailboxId}/export
```

Exports all visible categories and their published articles as a flat list (useful for AI training, site search indexing, etc.). Each category includes a `parent_id` field.

Pass `?nested=true` to receive a fully hierarchical structure where each category contains a `subcategories` array:

```
GET /api/knowledgebase/{mailboxId}/export?nested=true
```

**Response (nested):**
```json
{
  "mailbox_id": 1,
  "name": "My Mailbox",
  "generated_at": "2026-03-17T10:00:00+00:00",
  "categories": [
    {
      "id": 3,
      "name": "Getting Started",
      "description": "...",
      "url": "...",
      "client_url": null,
      "articles": [
        {
          "id": 12,
          "title": "Quick start guide",
          "text": "...",
          "status": 1,
          "url": "...",
          "client_url": null
        }
      ],
      "subcategories": [
        {
          "id": 7,
          "name": "Installation",
          "articles": [...],
          "subcategories": []
        }
      ]
    }
  ]
}
```

---

## Query Parameters

| Parameter | Endpoints | Description | Default |
|-----------|-----------|-------------|---------|
| `token` | All | API token — **required** on every request | — |
| `locale` / `lang` | All | Language code for returned content (e.g. `en`, `de`). `lang` takes priority over `locale`. | Mailbox default |
| `nested` | `/categories`, `/export` | `true` to return a recursive tree; `false` (default) returns a flat list with `parent_id` | `false` |
| `q` | `/search` | Search keyword — required for this endpoint | — |
| `limit` | `/popular` | Maximum number of results to return | `5` |
| `type` | `/popular` | Filter by content type: `all`, `articles`, `categories` | `all` |
| `include_hidden` | `/export` | Include hidden / draft categories and articles | `false` |

---

## Custom URL Templates

Configure under **Settings → Knowledge Base API**.

| Placeholder | Replaced with |
|-------------|--------------|
| `[mailbox]` | Mailbox ID |
| `[category]` | Category ID |
| `[article]` | Article ID (omitted automatically for category URLs) |

**Example — Client URL Template:**
```
https://your-site.com/docs?category=[category]&article=[article]
```

- Article 10 in category 5 → `client_url: "https://your-site.com/docs?category=5&article=10"`
- Category 5 → `client_url: "https://your-site.com/docs?category=5"`

Leave both templates empty to use the default FreeScout KB URLs.

---

## Usage Examples

**Flat category list:**
```bash
curl "https://example.com/api/knowledgebase/1/categories?token=YOUR_TOKEN"
```

**Nested category tree:**
```bash
curl "https://example.com/api/knowledgebase/1/categories?nested=true&token=YOUR_TOKEN"
```

**Category with subcategories and articles:**
```bash
curl "https://example.com/api/knowledgebase/1/categories/5?token=YOUR_TOKEN"
```

**Single article:**
```bash
curl "https://example.com/api/knowledgebase/1/categories/5/articles/10?token=YOUR_TOKEN"
```

**Search:**
```bash
curl "https://example.com/api/knowledgebase/1/search?q=installation&token=YOUR_TOKEN"
```

**Popular articles (top 10):**
```bash
curl "https://example.com/api/knowledgebase/1/popular?type=articles&limit=10&token=YOUR_TOKEN"
```

**Flat export with hidden content:**
```bash
curl "https://example.com/api/knowledgebase/1/export?include_hidden=true&token=YOUR_TOKEN"
```

**Nested export:**
```bash
curl "https://example.com/api/knowledgebase/1/export?nested=true&token=YOUR_TOKEN"
```

**Locale / language filter:**
```bash
curl "https://example.com/api/knowledgebase/1/categories?lang=de&token=YOUR_TOKEN"
```

**JavaScript fetch:**
```javascript
fetch("https://example.com/api/knowledgebase/1/categories?nested=true&token=YOUR_TOKEN")
  .then(res => res.json())
  .then(data => console.log(data));
```

---

## Screenshots

### Robust API with built-in response testing
![image](https://github.com/user-attachments/assets/12766225-0362-46ee-a40c-c177304e6114)

### Full analytics — understand what your customers care about most
<img width="2525" alt="image" src="https://github.com/user-attachments/assets/24a17b87-853f-4d0c-918a-14de0c84e259" />
<img width="2525" alt="image" src="https://github.com/user-attachments/assets/fcaa7d25-3044-44fc-8444-ff51ac9163dc" />
<img width="2525" alt="image" src="https://github.com/user-attachments/assets/3cf9f8d4-4c31-470c-81c2-f3394d1d53c0" />
<img width="2525" alt="image" src="https://github.com/user-attachments/assets/b6cedbc6-d36a-4554-8cb7-717fb6f311dd" />

### Identify gaps in your documentation
<img width="2525" alt="image" src="https://github.com/user-attachments/assets/bb3574c4-7fff-4a4e-8cde-431960cb75d4" />

---

## Credits

Originally created by [jtorvald](https://github.com/jtorvald/freescout-knowledge-api) and extended by [EcomGraduates](https://github.com/EcomGraduates/KnowledgeBaseApiModule).

## Contributing

Pull requests are welcome.

## Changelog

### 2.1.0
- **Nested categories** — flat list (default) now includes `parent_id` on every category; pass `?nested=true` on `/categories` or `/export` for a recursive tree response
- **`subcategories`** field added to `/categories/{id}` response (direct children of the requested category)
- **`lang` parameter** — alias for `locale`; accepted on all endpoints (`lang` takes priority)
- `resolveLocale()` helper centralises locale resolution across all endpoints
- Tree building uses a single `getTree(0, true)` call and pure PHP iteration — avoids raw `WHERE parent_id` SQL queries that fail on older KB module versions

### 2.0.0
- Added article and category view tracking
- Analytics dashboard with Chart.js visualisations (bar/pie charts, tabbed interface)
- Search query tracking (queries, result counts, success rates)
- New endpoints: `/popular` and `/export`
- Custom URL templates (`[mailbox]`, `[category]`, `[article]` placeholders)
- Built-in API testing interface in the settings page

### 1.0.2
- Token-based authentication added by EcomGraduates

### 1.0.1
- Initial release by jtorvald (no authentication)

## License

MIT
