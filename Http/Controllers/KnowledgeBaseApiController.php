<?php

namespace Modules\KnowledgeBaseApiModule\Http\Controllers;

use App\Conversation;
use App\Mailbox;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;
use Modules\KnowledgeBase\Entities\KbCategory;
use Modules\KnowledgeBase\Entities\KbArticle;
use Modules\KnowledgeBase\Entities\KbArticleKbCategory;
use Modules\KnowledgeBaseApiModule\Models\KbCategoryViews;
use Modules\KnowledgeBaseApiModule\Models\KbArticleViews;
use Modules\KnowledgeBaseApiModule\Models\KbSearchQuery;

class KnowledgeBaseApiController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Token validation is handled by middleware

        // DEBUG: log a stack trace whenever a query containing parent_id hits the DB.
        // Remove this block once the source is identified.
        \DB::listen(function ($query) {
            if (stripos($query->sql, 'parent_id') !== false) {
                $trace = collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30))
                    ->map(fn($f) => ($f['file'] ?? '?').':'.(($f['line']) ?? '?').' -> '.($f['class'] ?? '').($f['type'] ?? '').($f['function'] ?? ''))
                    ->implode("\n");
                \Log::error("[KbApiDebug] parent_id query detected.\nSQL: {$query->sql}\nBindings: ".json_encode($query->bindings)."\nTrace:\n{$trace}");
            }
        });
    }

    /**
     * Get all categories for a mailbox.
     *
     * @param Request $request
     * @param int $mailboxId
     * @return \Illuminate\Http\JsonResponse
     */
    public function get(Request $request, $mailboxId)
    {
        try {
            $mailbox = Mailbox::findOrFail($mailboxId);
            if ($mailbox === null) {
                return Response::json(['error' => 'Mailbox not found'], 404);
            }

            $locale = $this->resolveLocale($request, $mailbox);
            $nested = filter_var($request->input('nested', false), FILTER_VALIDATE_BOOLEAN);

            // Load all categories via a plain WHERE mailbox_id query — avoids the
            // WHERE parent_id = 0 SQL that getTree() always appends, which crashes on
            // KB module versions that don't have a parent_id column.
            $allCategories = \KbCategory::query()->setEagerLoads([])->where('mailbox_id', $mailbox->id)->get()->each(fn($c) => $c->setRelation('children', collect()))->all();

            if ($nested) {
                $items = $this->buildCategoryTree($allCategories, 0, $mailbox->id, $locale);
            } else {
                $items = $this->buildCategoryFlat($allCategories, $mailbox->id, $locale);
            }

            return Response::json([
                'mailbox_id' => $mailbox->id,
                'name' => $mailbox->name,
                'categories' => $items,
            ], 200);
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get a specific category with its articles.
     *
     * @param Request $request
     * @param int $mailboxId
     * @param int $categoryId
     * @return \Illuminate\Http\JsonResponse
     */
    public function category(Request $request, $mailboxId, $categoryId)
    {
        try {
            $mailbox = Mailbox::findOrFail($mailboxId);
            if ($mailbox === null) {
                return Response::json(['error' => 'Mailbox not found'], 404);
            }

            $category = KbCategory::query()->setEagerLoads([])->findOrFail($categoryId);
            $category->setRelation('children', collect());
            if (!$category->checkVisibility()) {
                $category = null;
            }
            if ($category === null) {
                return Response::json(['error' => 'Category not found or not visible'], 404);
            }

            // Track category view
            KbCategoryViews::incrementViews($categoryId, $mailboxId);

            $locale = $this->resolveLocale($request, $mailbox);

            $sortedArticles = $category->getArticlesSorted(true);
            $articles = [];
            foreach ($sortedArticles as $a) {
                $a->setLocale($locale);
                $articles[] = [
                    'id'         => $a->id,
                    'title'      => $a->getAttributeInLocale('title', $locale),
                    'text'       => $a->getAttributeInLocale('text', $locale),
                    'url'        => $this->buildArticleUrl($mailbox->id, $category->id, $a->id),
                    'client_url' => $this->buildClientArticleUrl($mailbox->id, $category->id, $a->id),
                ];
            }

            // Build subcategories by filtering the full flat list in PHP —
            // avoids generating WHERE parent_id = ? SQL
            $allCategories = \KbCategory::query()->setEagerLoads([])->where('mailbox_id', $mailbox->id)->get()->each(fn($c) => $c->setRelation('children', collect()))->all();
            $subcategories = [];
            foreach ($allCategories as $c) {
                if ((int)($c->parent_id ?? 0) !== (int)$category->id) {
                    continue;
                }
                if (!$c->checkVisibility()) {
                    continue;
                }
                $childArticleCount = method_exists($c, 'getArticlesSorted')
                    ? count($c->getArticlesSorted(true))
                    : 0;
                $subcategories[] = [
                    'id'            => $c->id,
                    'name'          => $c->getAttributeInLocale('name', $locale),
                    'description'   => $c->getAttributeInLocale('description', $locale),
                    'url'           => $this->buildCategoryUrl($mailbox->id, $c->id),
                    'client_url'    => $this->buildClientCategoryUrl($mailbox->id, $c->id),
                    'article_count' => $childArticleCount,
                ];
            }

            return Response::json([
                'id'         => 0,
                'mailbox_id' => $mailbox->id,
                'name'       => $mailbox->name,
                'category'   => [
                    'id'            => $category->id,
                    'name'          => $category->getAttributeInLocale('name', $locale),
                    'description'   => $category->getAttributeInLocale('description', $locale),
                    'url'           => $this->buildCategoryUrl($mailbox->id, $category->id),
                    'client_url'    => $this->buildClientCategoryUrl($mailbox->id, $category->id),
                    'subcategories' => $subcategories,
                ],
                'articles' => $articles,
            ], 200);
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Search for articles by keyword.
     *
     * @param Request $request
     * @param int $mailboxId
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request, $mailboxId)
    {
        try {
            $mailbox = Mailbox::findOrFail($mailboxId);
            if ($mailbox === null) {
                return Response::json(['error' => 'Mailbox not found'], 404);
            }

            $keyword = $request->input('q');
            if (empty($keyword)) {
                return Response::json(['error' => 'Search keyword is required'], 400);
            }

            $locale = $this->resolveLocale($request, $mailbox);

            // Convert keyword to lowercase for case-insensitive search
            $keyword = mb_strtolower($keyword);

            // Search in published articles only, using case-insensitive search
            $articles = KbArticle::where('mailbox_id', $mailbox->id)
                ->where(function($query) use ($keyword) {
                    $query->whereRaw('LOWER(title) LIKE ?', ['%'.$keyword.'%'])
                          ->orWhereRaw('LOWER(text) LIKE ?', ['%'.$keyword.'%']);
                })
                ->where('status', KbArticle::STATUS_PUBLISHED)
                ->get();

            $results = [];
            foreach ($articles as $article) {
                // Get categories for this article and check if at least one is visible
                $hasVisibleCategory = false;
                $categories = [];

                foreach ($article->categories as $category) {
                    // Only include visible categories
                    if (method_exists($category, 'checkVisibility') && $category->checkVisibility()) {
                        $hasVisibleCategory = true;
                        $categories[] = [
                            'id'   => $category->id,
                            'name' => $category->getAttributeInLocale('name', $locale),
                        ];
                    }
                }

                // Only show articles with at least one visible category
                if ($hasVisibleCategory) {
                    // Get the first visible category ID for URL construction
                    $firstCategoryId = $categories[0]['id'];

                    $results[] = [
                        'id'         => $article->id,
                        'title'      => $article->getAttributeInLocale('title', $locale),
                        'text'       => $article->getAttributeInLocale('text', $locale),
                        'categories' => $categories,
                        'url'        => $this->buildArticleUrl($mailbox->id, $firstCategoryId, $article->id),
                        'client_url' => $this->buildClientArticleUrl($mailbox->id, $firstCategoryId, $article->id),
                    ];
                }
            }

            // Track this search query
            KbSearchQuery::trackSearch($mailbox->id, $keyword, count($results), $locale);

            return Response::json([
                'mailbox_id' => $mailbox->id,
                'keyword'    => $keyword,
                'count'      => count($results),
                'results'    => $results,
            ], 200);
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get a specific article by its ID within a category.
     *
     * @param Request $request
     * @param int $mailboxId
     * @param int $categoryId
     * @param int $articleId
     * @return \Illuminate\Http\JsonResponse
     */
    public function article(Request $request, $mailboxId, $categoryId, $articleId)
    {
        try {
            // Check if mailbox exists
            $mailbox = Mailbox::findOrFail($mailboxId);
            if ($mailbox === null) {
                return Response::json(['error' => 'Mailbox not found'], 404);
            }

            // Check if category exists and is visible
            $category = KbCategory::query()->setEagerLoads([])->findOrFail($categoryId);
            $category->setRelation('children', collect());
            if (!$category->checkVisibility()) {
                return Response::json(['error' => 'Category not found or not visible'], 404);
            }

            // Get the article
            $article = KbArticle::where('id', $articleId)
                ->where('mailbox_id', $mailboxId)
                ->where('status', KbArticle::STATUS_PUBLISHED)
                ->first();

            if (!$article) {
                return Response::json(['error' => 'Article not found or not published'], 404);
            }

            // Check if article belongs to the specified category
            $belongs = false;
            foreach ($article->categories as $cat) {
                if ($cat->id == $categoryId) {
                    $belongs = true;
                    break;
                }
            }

            if (!$belongs) {
                return Response::json(['error' => 'Article does not belong to the specified category'], 404);
            }

            // Track article view
            KbArticleViews::incrementViews($articleId, $categoryId, $mailboxId);

            // Get locale
            $locale = $this->resolveLocale($request, $mailbox);
            $article->setLocale($locale);

            return Response::json([
                'mailbox_id'   => $mailbox->id,
                'mailbox_name' => $mailbox->name,
                'category'     => [
                    'id'         => $category->id,
                    'name'       => $category->getAttributeInLocale('name', $locale),
                    'url'        => $this->buildCategoryUrl($mailbox->id, $category->id),
                    'client_url' => $this->buildClientCategoryUrl($mailbox->id, $category->id),
                ],
                'article' => [
                    'id'         => $article->id,
                    'title'      => $article->getAttributeInLocale('title', $locale),
                    'text'       => $article->getAttributeInLocale('text', $locale),
                    'url'        => $this->buildArticleUrl($mailbox->id, $category->id, $article->id),
                    'client_url' => $this->buildClientArticleUrl($mailbox->id, $category->id, $article->id),
                ],
            ], 200);
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get popular articles and categories based on view counts.
     *
     * @param Request $request
     * @param int $mailboxId
     * @return \Illuminate\Http\JsonResponse
     */
    public function popular(Request $request, $mailboxId)
    {
        try {
            $mailbox = Mailbox::findOrFail($mailboxId);
            if ($mailbox === null) {
                return Response::json(['error' => 'Mailbox not found'], 404);
            }

            $limit  = (int) $request->input('limit', 5);
            $type   = $request->input('type', 'all');
            $locale = $this->resolveLocale($request, $mailbox);

            $response = [
                'mailbox_id' => $mailbox->id,
                'name'       => $mailbox->name,
            ];

            // Get popular categories if requested
            if ($type === 'all' || $type === 'categories') {
                $popularCategories = [];

                $topCategories = \Modules\KnowledgeBaseApiModule\Models\KbCategoryViews::where('mailbox_id', $mailbox->id)
                    ->orderBy('view_count', 'desc')
                    ->limit($limit)
                    ->get();

                foreach ($topCategories as $categoryView) {
                    $category = KbCategory::query()->setEagerLoads([])->find($categoryView->category_id);
                    if ($category) $category->setRelation('children', collect());
                    if ($category && $category->checkVisibility()) {
                        $popularCategories[] = [
                            'id'          => $category->id,
                            'name'        => $category->getAttributeInLocale('name', $locale),
                            'description' => $category->getAttributeInLocale('description', $locale),
                            'view_count'  => $categoryView->view_count,
                            'url'         => $this->buildCategoryUrl($mailbox->id, $category->id),
                            'client_url'  => $this->buildClientCategoryUrl($mailbox->id, $category->id),
                        ];
                    }
                }

                $response['popular_categories'] = $popularCategories;
            }

            // Get popular articles if requested
            if ($type === 'all' || $type === 'articles') {
                $popularArticles = [];

                $topArticles = \Modules\KnowledgeBaseApiModule\Models\KbArticleViews::where('mailbox_id', $mailbox->id)
                    ->orderBy('view_count', 'desc')
                    ->limit($limit)
                    ->get();

                foreach ($topArticles as $articleView) {
                    $article  = KbArticle::find($articleView->article_id);
                    $category = KbCategory::query()->setEagerLoads([])->find($articleView->category_id);
                    if ($category) $category->setRelation('children', collect());

                    if ($article && $article->status == KbArticle::STATUS_PUBLISHED && $category && $category->checkVisibility()) {
                        $popularArticles[] = [
                            'id'         => $article->id,
                            'title'      => $article->getAttributeInLocale('title', $locale),
                            'view_count' => $articleView->view_count,
                            'url'        => $this->buildArticleUrl($mailbox->id, $category->id, $article->id),
                            'client_url' => $this->buildClientArticleUrl($mailbox->id, $category->id, $article->id),
                            'category'   => [
                                'id'   => $category->id,
                                'name' => $category->getAttributeInLocale('name', $locale),
                            ],
                        ];
                    }
                }

                $response['popular_articles'] = $popularArticles;
            }

            return Response::json($response, 200);
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Export all KB content for AI training purposes.
     *
     * @param Request $request
     * @param int $mailboxId
     * @return \Illuminate\Http\JsonResponse
     */
    public function export(Request $request, $mailboxId)
    {
        try {
            $mailbox = Mailbox::findOrFail($mailboxId);
            if ($mailbox === null) {
                return Response::json(['error' => 'Mailbox not found'], 404);
            }

            $locale        = $this->resolveLocale($request, $mailbox);
            $includeHidden = filter_var($request->input('include_hidden', false), FILTER_VALIDATE_BOOLEAN);
            $nested        = filter_var($request->input('nested', false), FILTER_VALIDATE_BOOLEAN);

            // Load all categories once and filter in PHP
            $allCategories = \KbCategory::query()->setEagerLoads([])->where('mailbox_id', $mailbox->id)->get()->each(fn($c) => $c->setRelation('children', collect()))->all();

            $exportData = [
                'mailbox_id'   => $mailbox->id,
                'name'         => $mailbox->name,
                'categories'   => [],
                'generated_at' => now()->toIso8601String(),
            ];

            if ($nested) {
                $exportData['categories'] = $this->buildExportCategoryTree($allCategories, 0, $mailbox->id, $locale, $includeHidden);
            } else {
                foreach ($allCategories as $category) {
                    if (!$includeHidden && !$category->checkVisibility()) {
                        continue;
                    }

                    $articles = method_exists($category, 'getArticlesSorted')
                        ? $category->getArticlesSorted(!$includeHidden)
                        : [];

                    $articleData = [];
                    foreach ($articles as $article) {
                        $article->setLocale($locale);
                        $articleData[] = [
                            'id'         => $article->id,
                            'title'      => $article->getAttributeInLocale('title', $locale),
                            'text'       => $article->getAttributeInLocale('text', $locale),
                            'status'     => $article->status,
                            'url'        => $this->buildArticleUrl($mailbox->id, $category->id, $article->id),
                            'client_url' => $this->buildClientArticleUrl($mailbox->id, $category->id, $article->id),
                        ];
                    }

                    $exportData['categories'][] = [
                        'id'          => $category->id,
                        'parent_id'   => $category->parent_id ? (int)$category->parent_id : null,
                        'name'        => $category->getAttributeInLocale('name', $locale),
                        'description' => $category->getAttributeInLocale('description', $locale),
                        'url'         => $this->buildCategoryUrl($mailbox->id, $category->id),
                        'client_url'  => $this->buildClientCategoryUrl($mailbox->id, $category->id),
                        'articles'    => $articleData,
                    ];
                }
            }

            return Response::json($exportData, 200);
        } catch (\Exception $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Resolve locale from request, preferring `lang` over `locale`, falling back to mailbox default.
     *
     * @param Request $request
     * @param \App\Mailbox $mailbox
     * @return string
     */
    private function resolveLocale(Request $request, $mailbox): string
    {
        return $request->input('lang')
            ?? $request->input('locale')
            ?? \Kb::defaultLocale($mailbox);
    }

    /**
     * Recursively build a nested category tree from a pre-loaded flat list.
     * Reads parent_id from already-loaded Eloquent attributes — never generates SQL.
     * Root categories have parent_id = 0 (or null) in FreeScout's schema.
     *
     * @param array  $allCategories  Full flat list from KbCategory::where('mailbox_id', ...)->get()->all()
     * @param int    $parentId       0 for root level
     * @param int    $mailboxId
     * @param string $locale
     * @return array
     */
    private function buildCategoryTree(array $allCategories, int $parentId, int $mailboxId, string $locale): array
    {
        $tree = [];
        foreach ($allCategories as $c) {
            if ((int)($c->parent_id ?? 0) !== $parentId) {
                continue;
            }
            if (!$c->checkVisibility()) {
                continue;
            }

            $articleCount = method_exists($c, 'getArticlesSorted')
                ? count($c->getArticlesSorted(true))
                : 0;

            $tree[] = [
                'id'            => $c->id,
                'name'          => $c->getAttributeInLocale('name', $locale),
                'description'   => $c->getAttributeInLocale('description', $locale),
                'url'           => $this->buildCategoryUrl($mailboxId, $c->id),
                'client_url'    => $this->buildClientCategoryUrl($mailboxId, $c->id),
                'article_count' => $articleCount,
                'children'      => $this->buildCategoryTree($allCategories, (int)$c->id, $mailboxId, $locale),
            ];
        }
        return $tree;
    }

    /**
     * Build a flat list of visible categories with parent_id references.
     *
     * @param array  $allCategories  Full flat list from KbCategory::where('mailbox_id', ...)->get()->all()
     * @param int    $mailboxId
     * @param string $locale
     * @return array
     */
    private function buildCategoryFlat(array $allCategories, int $mailboxId, string $locale): array
    {
        $flat = [];
        foreach ($allCategories as $c) {
            if (!$c->checkVisibility()) {
                continue;
            }

            $articleCount = method_exists($c, 'getArticlesSorted')
                ? count($c->getArticlesSorted(true))
                : 0;

            $flat[] = [
                'id'            => $c->id,
                'parent_id'     => $c->parent_id ? (int)$c->parent_id : null,
                'name'          => $c->getAttributeInLocale('name', $locale),
                'description'   => $c->getAttributeInLocale('description', $locale),
                'url'           => $this->buildCategoryUrl($mailboxId, $c->id),
                'client_url'    => $this->buildClientCategoryUrl($mailboxId, $c->id),
                'article_count' => $articleCount,
            ];
        }
        return $flat;
    }

    /**
     * Recursively build a nested export category tree from a pre-loaded flat list.
     *
     * @param array  $allCategories  Full flat list from KbCategory::where('mailbox_id', ...)->get()->all()
     * @param int    $parentId       0 for root level
     * @param int    $mailboxId
     * @param string $locale
     * @param bool   $includeHidden
     * @return array
     */
    private function buildExportCategoryTree(array $allCategories, int $parentId, int $mailboxId, string $locale, bool $includeHidden): array
    {
        $tree = [];
        foreach ($allCategories as $category) {
            if ((int)($category->parent_id ?? 0) !== $parentId) {
                continue;
            }
            if (!$includeHidden && !$category->checkVisibility()) {
                continue;
            }

            $articles = method_exists($category, 'getArticlesSorted')
                ? $category->getArticlesSorted(!$includeHidden)
                : [];

            $articleData = [];
            foreach ($articles as $article) {
                $article->setLocale($locale);
                $articleData[] = [
                    'id'         => $article->id,
                    'title'      => $article->getAttributeInLocale('title', $locale),
                    'text'       => $article->getAttributeInLocale('text', $locale),
                    'status'     => $article->status,
                    'url'        => $this->buildArticleUrl($mailboxId, $category->id, $article->id),
                    'client_url' => $this->buildClientArticleUrl($mailboxId, $category->id, $article->id),
                ];
            }

            $tree[] = [
                'id'            => $category->id,
                'name'          => $category->getAttributeInLocale('name', $locale),
                'description'   => $category->getAttributeInLocale('description', $locale),
                'url'           => $this->buildCategoryUrl($mailboxId, $category->id),
                'client_url'    => $this->buildClientCategoryUrl($mailboxId, $category->id),
                'articles'      => $articleData,
                'subcategories' => $this->buildExportCategoryTree($allCategories, (int)$category->id, $mailboxId, $locale, $includeHidden),
            ];
        }
        return $tree;
    }

    /**
     * Build article URL based on settings.
     *
     * @param int $mailboxId
     * @param int $categoryId
     * @param int $articleId
     * @return string
     */
    private function buildArticleUrl($mailboxId, $categoryId, $articleId)
    {
        $customUrlTemplate = \App\Option::get('knowledgebase_api_custom_url');

        if (!empty($customUrlTemplate)) {
            return str_replace(
                ['[mailbox]', '[category]', '[article]'],
                [$mailboxId, $categoryId, $articleId],
                $customUrlTemplate
            );
        }

        return url('/kb/article/'.$articleId);
    }

    /**
     * Build client-side article URL based on settings.
     *
     * @param int $mailboxId
     * @param int $categoryId
     * @param int $articleId
     * @return string|null
     */
    private function buildClientArticleUrl($mailboxId, $categoryId, $articleId)
    {
        $clientUrlTemplate = \App\Option::get('knowledgebase_api_client_url');

        if (!empty($clientUrlTemplate)) {
            return str_replace(
                ['[mailbox]', '[category]', '[article]'],
                [$mailboxId, $categoryId, $articleId],
                $clientUrlTemplate
            );
        }

        return null;
    }

    /**
     * Build category URL based on settings.
     *
     * @param int $mailboxId
     * @param int $categoryId
     * @return string
     */
    private function buildCategoryUrl($mailboxId, $categoryId)
    {
        $customUrlTemplate = \App\Option::get('knowledgebase_api_custom_url');

        if (!empty($customUrlTemplate)) {
            $categoryTemplate = str_replace('[article]', '', $customUrlTemplate);
            $categoryTemplate = rtrim($categoryTemplate, '/');

            return str_replace(
                ['[mailbox]', '[category]'],
                [$mailboxId, $categoryId],
                $categoryTemplate
            );
        }

        return url('/kb/category/'.$categoryId);
    }

    /**
     * Build client-side category URL based on settings.
     *
     * @param int $mailboxId
     * @param int $categoryId
     * @return string|null
     */
    private function buildClientCategoryUrl($mailboxId, $categoryId)
    {
        $clientUrlTemplate = \App\Option::get('knowledgebase_api_client_url');

        if (!empty($clientUrlTemplate)) {
            $categoryTemplate = str_replace('[article]', '', $clientUrlTemplate);
            $categoryTemplate = rtrim($categoryTemplate, '/&');

            return str_replace(
                ['[mailbox]', '[category]'],
                [$mailboxId, $categoryId],
                $categoryTemplate
            );
        }

        return null;
    }
}
