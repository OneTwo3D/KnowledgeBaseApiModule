<?php

namespace Modules\KnowledgeBase\Http\Controllers;

use App\Customer;
use App\Mailbox;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\KnowledgeBase\Entities\KbCategory;
use Modules\KnowledgeBase\Entities\KbArticle;
use Modules\KnowledgeBase\Entities\KbArticleKbCategory;
use Validator;

class KnowledgeBaseController extends Controller
{
    /**
     * Settings.
     */
    public function settings($mailbox_id)
    {
        $mailbox = Mailbox::findOrFail($mailbox_id);
        
        if (!\Kb::canEditKb()) {
            \Helper::denyAccess();
        }

        if (!auth()->user()->can('view', $mailbox)) {
            \Helper::denyAccess();
        }

        $meta_settings = $mailbox->meta['kb'] ?? [];

        $kb_locales = $meta_settings['locales'] ?? [];

        $settings = [
            'site_name' => $meta_settings['site_name'] ?? __('{%mailbox.name%} Knowledge Base'),
            'domain' => $meta_settings['domain'] ?? '',
            'footer' => $meta_settings['footer'] ?? '&copy; {%year%} {%mailbox.name%}',
            'menu' => $meta_settings['menu'] ?? '',
            'locales' => $kb_locales,
            'visibility' => $meta_settings['visibility'] ?? \Kb::VISIBILITY_PUBLIC,
        ];

        $widget_settings = \Kb::getWidgetSettings($mailbox_id);

        if (!empty($widget_settings)) {
            $widget_settings['id'] = \Kb::encodeMailboxId($mailbox_id, \Kb::WIDGET_SALT);
        }

        // Merge system locales with KB locales.
        $locales = array_merge($kb_locales, \Helper::getAllLocales());

        return view('knowledgebase::settings', [
            'mailbox'  => $mailbox,
            'settings' => $settings,
            'locales'  => $locales,
            'widget_settings'   => $widget_settings,
        ]);
    }

    /**
     * Settings save.
     */
    public function settingsSave(Request $request, $mailbox_id)
    {
        $mailbox = Mailbox::findOrFail($mailbox_id);

        if (!\Kb::canEditKb()) {
            \Helper::denyAccess();
        }
        
        if (!empty($request->kb_action) && $request->kb_action == 'save_settings') {
            $settings = $request->settings;

            if (!empty($request->settings['locales']) && count($request->settings['locales']) == 1) {
                $settings['locales'] = [];
            }

            $settings['footer'] = \Helper::stripDangerousTags($settings['footer'] ?? '');

            $mailbox->setMetaParam('kb', $settings);
            $mailbox->save();

            \Session::flash('flash_success_floating', __('Settings updated'));
        }

        if (!empty($request->kb_action) && $request->kb_action == 'save_widget') {
            $settings = $request->widget ?? [];

            unset($settings['_token']);
            unset($settings['kb_action']);

            if (array_key_exists('locale', $settings) && !$settings['locale']) {
                unset($settings['locale']);
            }

            if (empty($settings['color'])) {
                $settings['color'] = \Kb::getDefaultWidgetSettings()['color'];
            }

            try {
                \Kb::saveWidgetSettings($mailbox_id, $settings);

                \Session::flash('flash_success_floating', __('Settings updated'));
                
            } catch (\Exception $e) {
                \Session::flash('flash_error_floating', $e->getMessage());
            }
        }

        return redirect()->route('mailboxes.knowledgebase.settings', ['mailbox_id' => $mailbox_id]);
    }

    /**
     * Categories.
     */
    public function categories($mailbox_id)
    {
        $mailbox = Mailbox::findOrFail($mailbox_id);
        
        if (!\Kb::canEditKb()) {
            \Helper::denyAccess();
        }

        \Kb::$use_primary_if_empty = false;

        return view('knowledgebase::categories', [
            'mailbox'   => $mailbox,
            
        ]);
    }

    /**
     * Categories save.
     */
    public function categoriesSave(Request $request, $mailbox_id)
    {
        $mailbox = Mailbox::findOrFail($mailbox_id);

        if (!\Kb::canEditKb()) {
            \Helper::denyAccess();
        }

        if ($request->action == 'update') {
            if (!empty($request->category_id)) {
                $category = \KbCategory::find($request->category_id);
                if ($category) {
                    $category->fill($request->all());
                    $category->expand = $request->expand ?? false;

                    // Set translations.
                    foreach (\KbCategory::$translatable_fields as $field) {
                        if (is_array($request->$field)) {
                            foreach ($request->$field as $locale => $value) {
                                $category->setAttributeInLocale($field, $value, $locale);
                            }
                        }
                    }

                    try {
                        $category->save();
                        \Session::flash('flash_success_floating', __('Category updated'));
                    } catch (\Exception $e) {
                        \Session::flash('flash_error_floating', __('Category with such name already exists'));
                    }
                }
            }
        } else {
            $category = new \KbCategory();
            $category->fill($request->all());
            // Set translations.
            foreach (\KbCategory::$translatable_fields as $field) {
                if (is_array($request->$field)) {
                    foreach ($request->$field as $locale => $value) {
                        $category->setAttributeInLocale($field, $value, $locale);
                    }
                }
            }
            //try {
            $category->save();
            \Session::flash('flash_success_floating', __('Category created'));
            // } catch (\Exception $e) {
            //     \Session::flash('flash_error_floating', __('Category with such name already exists'));
            // }
        }

        return redirect()->route('mailboxes.knowledgebase.categories', ['mailbox_id' => $mailbox_id]);
    }

    /**
     * Conversations ajax controller.
     */
    public function ajaxAdmin(Request $request)
    {
        $response = [
            'status' => 'error',
            'msg'    => '', // this is error message
        ];

        if (!\Kb::canEditKb()) {
            $response['msg'] = __('Not enough permissions');
        }
        
        switch ($request->action) {

            // Categories.
            case 'update_categories_sort_order':

                if (!$response['msg']) {

                    $categories = \KbCategory::whereIn('id', $request->categories)->select('id', 'sort_order')->get();

                    if (count($categories)) {
                        foreach ($request->categories as $i => $request_category_id) {
                            foreach ($categories as $category) {
                                if ($category->id != $request_category_id) {
                                    continue;
                                }
                                $category->sort_order = $i+1;
                                $category->save();
                            }
                        }
                        $response['status'] = 'success';
                    }
                }
                break;

            // Articles.
            case 'update_articles_sort_order':

                if (!$response['msg']) {

                    $articles_to_category = KbArticleKbCategory::whereIn('kb_article_id', $request->articles)->get();

                    if (count($articles_to_category)) {
                        foreach ($request->articles as $i => $request_article_id) {
                            foreach ($articles_to_category as $article_to_category) {
                                if ($article_to_category->kb_article_id != $request_article_id) {
                                    continue;
                                }
                                $article_to_category->sort_order = $i+1;
                                $article_to_category->save();
                            }
                        }
                        $response['status'] = 'success';
                    }
                }
                break;

            case 'update_category_articles_order':

                if (!$response['msg']) {

                    $category = KbCategory::find($request->category_id);

                    if ($category) {
                        $category->articles_order = (int)$request->articles_order;
                        $category->save();
                        $response['status'] = 'success';
                    }
                }
                break;

            case 'delete_category':

                if (!$response['msg']) {

                    $category = KbCategory::find($request->category_id);

                    if ($category) {
                        KbArticleKbCategory::where('kb_category_id', $category->id)->delete();
                        KbCategory::where('kb_category_id', $category->id)->update(['kb_category_id' => null]);
                        $category->delete();
                        $response['status'] = 'success';
                    }
                }
                break;

            case 'delete_article':
                if (!$response['msg']) {

                    $article = KbArticle::find($request->article_id);

                    if ($article) {
                        KbArticleKbCategory::where('kb_article_id', $article->id)->delete();
                        $article->delete();
                        $response['status'] = 'success';
                    }
                }
                break;

            case 'reference_search':
                $response['msg'] = '';

                $q = trim($request->q ?? '');
                $like = '%'.mb_strtolower($q).'%';

                $mailboxes = auth()->user()->mailboxesCanView();
                $mailbox_ids = $mailboxes->pluck('id');

                $articles = \KbArticle::whereIn('mailbox_id', $mailbox_ids)
                    ->where('status', \KbArticle::STATUS_PUBLISHED)
                    ->where(function ($query_like) use ($like) {
                        $query_like->whereRaw('lower(title) like ?', $like)
                            ->orWhereRaw('lower(text) like ?', $like);
                    })
                    ->get();

                $response['status'] = 'success';
                $response['html'] = '';

                if (count($articles)) {
                    $items = [];

                    // Select articles containing searched text.
                    \Kb::$use_primary_if_empty = false;
                    foreach ($articles as $i => $article) {

                        // Get article mailbox.
                        $mailbox = $mailboxes->find($article->mailbox_id);
                        if (!$mailbox) {
                            continue;
                        }

                        $locales = \Kb::getLocales($mailbox);

                        if (!count($locales)) {
                            $locales = [''];
                        }

                        foreach ($locales as $locale) {
                            $article->setLocale($locale);

                            if (!$q || (mb_stristr($article->title, $q) || mb_stristr($article->text, $q))) {
                                $items[] = [
                                    'title' => $article->title,
                                    'url' => $article->urlFrontend($mailbox, null, $locale),
                                    'locale' => \Kb::defaultLocale($mailbox) == $locale ? '' : \Helper::getLocaleData($locale)['name'] ?? '',
                                ];
                            }
                        }
                    }

                    $response['html'] = \View::make('knowledgebase::partials/reference_search_table', [
                        'items' => $items
                    ])->render();
                }

                break;

            default:
                $response['msg'] = 'Unknown action';
                break;
        }

        if ($response['status'] == 'error' && empty($response['msg'])) {
            $response['msg'] = 'Unknown error occured';
        }

        return \Response::json($response);
    }

    /**
     * Articles.
     */
    public function articles($mailbox_id, $category_id = null)
    {
        $mailbox = Mailbox::findOrFail($mailbox_id);
        
        if (!\Kb::canEditKb()) {
            \Helper::denyAccess();
        }

        if ($category_id === null) {
            $category_id = session()->get('knowledgebase.articles_category_id');
        }

        if ($category_id && $category_id != -1) {
            $category = \KbCategory::find($category_id);
            if (!$category) {
                $category = null;
                $category_id = null;
            }
        }

        if ($category_id) {
            session()->put('knowledgebase.articles_category_id', (int)$category_id);
        }

        $articles = [];
        $category = null;

        if ($category_id && $category_id != -1) {
            if (!$category) {
                $category = \KbCategory::find($category_id);
            }
            if ($category) {
                $articles = $category->getArticlesSorted();
            }
        } elseif ($category_id == -1) {
            $category = new \KbCategory();
            $articles = \KbArticle::where('mailbox_id', $mailbox->id)->whereNotIn('id', KbArticleKbCategory::distinct('kb_article_id')->pluck('kb_article_id')->toArray())->get();
        } else {
            $category = new \KbCategory();
            $articles = \KbArticle::where('mailbox_id', $mailbox->id)->get();
        }

        \Kb::$use_primary_if_empty = false;

        return view('knowledgebase::articles', [
            'mailbox'   => $mailbox,
            'articles'   => $articles,
            'category'   => $category,
            'category_id'   => $category_id,
        ]);
    }

    /**
     * Create or update article.
     */
    public function article(Request $request, $mailbox_id, $entity_id = null)
    {
        $mailbox = Mailbox::findOrFail($mailbox_id);
        
        if (!\Kb::canEditKb()) {
            \Helper::denyAccess();
        }

        $category_id = null;
        $categories = [];
        
        if ($request->route()->getName() == 'mailboxes.knowledgebase.new_article') {
            // Create
            $mode = 'create';
            $category_id = $entity_id;
            $categories[] = $category_id;

            $article = new \KbArticle();
        } else {
            // Update
            $mode = 'update';
            $article = \KbArticle::findOrFail($entity_id);
            $categories = $article->categories->pluck('id')->toArray();
        }

        if ($request->kb_locale) {
            $article->setLocale($request->kb_locale);
        }

        return view('knowledgebase::article', [
            'mailbox'   => $mailbox,
            'article'   => $article,
            'categories'   => $categories,
            'category_id'   => $category_id,
            'mode'   => $mode,
        ]);
    }

    public function articleCreate(Request $request, $mailbox_id)
    {
        return $this->articleSave($request, $mailbox_id, null);
    }
    
    /**
     * Categories save.
     */
    public function articleSave(Request $request, $mailbox_id, $article_id = null)
    {
        $mailbox = Mailbox::findOrFail($mailbox_id);

        if (!\Kb::canEditKb()) {
            \Helper::denyAccess();
        }

        // Preview.

        if ($article_id) {
            // Update.
            $article = \KbArticle::findOrFail($article_id);
        } else {
            // Create.
            $article = new \KbArticle();
        }

        // Locale.
        if (\Kb::isMultilingual($mailbox) && $request->kb_locale) {
            $article->setLocale($request->kb_locale);
        }

        // Slug.
        if (!empty($request->slug)) {
            $request->merge(['slug' => \Kb::slugify($request->slug)]);
        } else {
            $request->merge(['slug' => \Kb::slugify($request->title)]);
        }

        $article->fill($request->all());

        $allowed_tags = explode(',', config('knowledgebase.allowed_tags') ?? '');
        $allowed_tags = array_map('trim', $allowed_tags);

        $article->text = \Helper::stripDangerousTags($article->text ?? '', $allowed_tags);

        if ($request->action == 'publish') {
            $article->status = \KbArticle::STATUS_PUBLISHED;
            \Session::flash('flash_success_floating', __('Article published'));
        } elseif ($request->action == 'unpublish') {
            $article->status = \KbArticle::STATUS_DRAFT;
            \Session::flash('flash_success_floating', __('Article unpublished'));
        } elseif ($article_id) {
            \Session::flash('flash_success_floating', __('Article updated'));
        } else {
            \Session::flash('flash_success_floating', __('Article saved'));
        }
        
        $article->save();

        // Categories.
        $article->categories()->sync($request->categories ?: []);

        return redirect()->route('mailboxes.knowledgebase.article', [
            'mailbox_id' => $mailbox_id,
            'article_id' => $article->id,
            'kb_locale'  => $request->kb_locale ?? '',
        ]);
    }

    public function frontendI18n(Request $request, $kb_locale, $mailbox_id)
    {
        return $this->frontend($request, $mailbox_id, $kb_locale);
    }

    /**
     * Frontend.
     */
    public function frontend(Request $request, $mailbox_id, $kb_locale = '')
    {
        $mailbox = $this->processMailboxId($mailbox_id);

        if (!$mailbox) {
            abort(404);
        }

        $redirect = $this->frontendProcessLocale($kb_locale, $mailbox);
        if ($redirect) {
            return $redirect;
        }

        $redirect = $this->frontendProcessVisibility($mailbox, $kb_locale);
        if ($redirect) {
            return $redirect;
        }

        $categories = \KbCategory::getTree($mailbox->id, [], 0, true);

        $articles = [];
        if (!count($categories)) {
            $articles = \KbArticle::where('status', \KbArticle::STATUS_PUBLISHED)
                ->where('mailbox_id', $mailbox->id)
                ->get();
            $articles = $articles->sortBy('sort_order');
            // Exclude Private articles if user is not authenticated.
            if (!auth()->user()) {
                $public_category_ids = KbCategory::getAllCached($mailbox->id)->pluck('id')->toArray();
                foreach ($articles as $i => $article) {
                    foreach ($article->categories as $category) {
                        if (in_array($category->id, $public_category_ids)) {
                            continue 2;
                        }
                    }
                    $articles->forget($i);
                }
            }
        }

        return view('knowledgebase::frontend/frontend', [
            'mailbox' => $mailbox,
            'categories' => $categories,
            'articles' => $articles,
        ]);
    }

    public function frontendCategoryI18n(Request $request, $kb_locale, $mailbox_id, $category_id)
    {
        return $this->frontendCategory($request, $mailbox_id, $category_id, $kb_locale);
    }

    /**
     * Frontend category.
     */
    public function frontendCategory(Request $request, $mailbox_id, $category_id, $kb_locale = '')
    {
        $mailbox = $this->processMailboxId($mailbox_id);

        if (!$mailbox) {
            abort(404);
        }

        $redirect = $this->frontendProcessLocale($kb_locale, $mailbox);
        if ($redirect) {
            return $redirect;
        }

        $redirect = $this->frontendProcessVisibility($mailbox, $kb_locale);
        if ($redirect) {
            return $redirect;
        }

        $category = KbCategory::findOrFail($request->category_id);
        
        $categories = \KbCategory::getTree($mailbox->id, [], 0, true);

        // Check visibility.
        if (!$category->checkVisibility()) {
            $category = null;
            // \Session::flash('flash_success_floating', __('This section is not accessible.'));
            // return redirect()->away(\Kb::getKbUrl($mailbox->id));
        }

        $articles = [];
        if ($category) {
            $articles = $category->getArticlesSorted(true);
        }

        if (count($articles) == 1) {
            return redirect()->to($articles[0]->urlFrontend($mailbox, $request->category_id));
        }

        return view('knowledgebase::frontend/category', [
            'mailbox' => $mailbox,
            'category' => $category,
            'categories' => $categories,
            'articles' => $articles,
        ]);
    }

    // todo: mailbox should be active.
    public function processMailboxId($mailbox_id)
    {
        try {
            $mailbox_id = \Kb::decodeMailboxId($mailbox_id);

            if ($mailbox_id) {
                $mailbox = Mailbox::findOrFail($mailbox_id);
            }
        } catch (\Exception $e) {
            return null;
        }

        if (empty($mailbox)) {
            return null;
        }

        return $mailbox;
    }

    /**
     * Frontend article (backward compatibility).
     */
    public function frontendArticleBackward(Request $request, $mailbox_id, $article_id)
    {
        $mailbox = $this->processMailboxId($mailbox_id);

        if (!$mailbox) {
            abort(404);
        }

        $article = KbArticle::findOrFail($request->article_id);

        return redirect()->to($article->urlFrontend($mailbox, $request->category_id ?? ''));
    }

    public function frontendArticleI18n(Request $request, $kb_locale, $mailbox_id, $article_id, $slug = '')
    {
        return $this->frontendArticle($request, $mailbox_id, $article_id, $slug, $kb_locale);
    }

    /**
     * Frontend article.
     */
    public function frontendArticle(Request $request, $mailbox_id, $article_id, $slug = '', $kb_locale = '')
    {
        $mailbox = $this->processMailboxId($mailbox_id);

        if (!$mailbox) {
            abort(404);
        }

        $redirect = $this->frontendProcessLocale($kb_locale, $mailbox);
        if ($redirect) {
            return $redirect;
        }

        $redirect = $this->frontendProcessVisibility($mailbox, $kb_locale);
        if ($redirect) {
            return $redirect;
        }

        $category = null;
        $categories = [];
        $related_articles = [];

        $article = KbArticle::findOrFail($request->article_id);

        if (!$article->isPublished()) {
            $article = null;
        } else {
            if ($request->category_id) {
                $category = KbCategory::findOrFail($request->category_id);
            } else {
                // If article is connected to some category redirect to the URL with this category_id.
                if (count($article->categories)) {
                    return redirect()->to($article->urlFrontend($mailbox, $article->categories[0]->id));
                }

                $category = new KbCategory();
            }
            
            // Make sure that article has no categories or has at least one visible.
            if (!$article->isVisible()) {
                $article = null;
            }

            $categories = \KbCategory::getTree($mailbox->id, [], 0, true);

            // Check visibility.
            if ($category->id && !$category->checkVisibility()) {
                $category = null;
            }
        }

        // Make sure there is a slug in URL.
        if ($article && $article->slug && (empty($request->slug) || $article->slug != $request->slug)) {
            return redirect()->to($article->urlFrontend($mailbox, $request->category_id ?? ''));
        }

        if ($category) {
            $related_articles = $category->getArticlesSorted(true);
            if (count($related_articles) < 2) {
                $related_articles = [];
            }
            foreach ($related_articles as $i => $related_article) {
                if ($related_article->id == $request->article_id) {
                    unset($related_articles[$i]);
                }
            }
        }

        return view('knowledgebase::frontend/article', [
            'mailbox' => $mailbox,
            'category' => $category,
            'categories' => $categories,
            'article' => $article,
            'related_articles' => $related_articles,
        ]);
    }

    public function frontendProcessLocale($locale, $mailbox)
    {
        $locales = \Kb::getLocales($mailbox);

        if (!empty($locales) &&
            (!in_array($locale, $locales) || !$locale)
        ) {
            // Redirect to default locale.
            return redirect()->to(\Kb::changeUrlLocale($locales[0]));
        } elseif ($locale && empty($locales)) {
            // Redirect without locale.
            return redirect()->to(\Kb::changeUrlLocale(''));
        } else {
            return null;
        }
        // if (!$locale) {
        //     $locale = \Kb::defaultLocale($mailbox);
        // }
        //\Kb::$locale = $locale;
    }

    public function frontendProcessVisibility($mailbox, $locale = '')
    {
        $exclude_routes = [
            'knowledgebase.customer_login',
            'knowledgebase.customer_login_i18n',
            'knowledgebase.customer_login_process',
            'knowledgebase.customer_login_process_i18n'
        ];

        $visibility = $mailbox->meta['kb']['visibility'] ?? \Kb::VISIBILITY_PUBLIC;

        if ($visibility == \Kb::VISIBILITY_PUBLIC) {
            // Access to customer login page is always allowed.
            if (in_array(request()->route()->getName(), $exclude_routes)) {
                return redirect()->route('knowledgebase.frontend.home', ['mailbox_id' => \Kb::encodeMailboxId($mailbox->id)]);
            }
        } elseif ($visibility == \Kb::VISIBILITY_USERS) {
            // Any authenticated user.
            if (!auth()->check()) {
                return redirect()->route('login');
            }
        } elseif ($visibility == \Kb::VISIBILITY_CUSTOMERS) {
            // Any user having access to the current Knowledge Base
            // or any existing customer.
            $customer = \Kb::authCustomer();
            if (!empty($mailbox->meta['kb']['domain'])) {
                $exclude_routes = array_merge($exclude_routes, [route('login')]);
            }
            if (!$customer
                // Access to customer login page or user login page is always allowed.
                && !in_array(request()->route()->getName(), $exclude_routes)
                && !\Kb::canEditKb()
            ) {
                return redirect()->route('knowledgebase.customer_login', [
                    'mailbox_id' => \Kb::encodeMailboxId($mailbox->id),
                    'return_url' => request()->fullUrl(),
                ]);
            }
        } elseif ($visibility == \Kb::VISIBILITY_USERS_CUSTOMERS) {
            // Any authenticated user or any existing customer.
            $customer = \Kb::authCustomer();
            if (!auth()->check() && !$customer && !in_array(request()->route()->getName(), $exclude_routes)) {
                return redirect()->route('knowledgebase.customer_login', [
                    'mailbox_id' => \Kb::encodeMailboxId($mailbox->id),
                    'return_url' => request()->fullUrl(),
                ]);
            }
        }

        return \Eventy::filter('knowledgebase.frontend_process_visibility', null, $mailbox, $visibility);
    }

    public function frontendSearchI18n(Request $request, $kb_locale, $mailbox_id)
    {
        return $this->frontendSearch($request, $mailbox_id, $kb_locale);
    }

    /**
     * Search.
     */
    public function frontendSearch(Request $request, $mailbox_id, $kb_locale = '')
    {
        $mailbox = $this->processMailboxId($mailbox_id);

        if (!$mailbox) {
            abort(404);
        }

        $redirect = $this->frontendProcessLocale($kb_locale, $mailbox);
        if ($redirect) {
            return $redirect;
        }

        $redirect = $this->frontendProcessVisibility($mailbox, $kb_locale);
        if ($redirect) {
            return $redirect;
        }

        $q = trim($request->q ?? '');

        $like = '%'.mb_strtolower($q).'%';

        $articles = \KbArticle::where('mailbox_id', $mailbox->id)
            ->where('status', \KbArticle::STATUS_PUBLISHED)
            ->where(function ($query_like) use ($like) {
                $query_like->whereRaw('lower(title) like ?', $like)
                    ->orWhereRaw('lower(text) like ?', $like);
            })
            ->get();

        \Kb::$use_primary_if_empty = false;

        // Remove non-available articles.
        foreach ($articles as $i => $article) {
            // Not visible for current visitor.
            if (!$article->isVisible()) {
                unset($articles[$i]);
            }
            // Not available in current language.
            if (!mb_stristr($article->title, $q) && !mb_stristr($article->text, $q)) {
                unset($articles[$i]);
            }
        }

        \Kb::$use_primary_if_empty = true;
        
        return view('knowledgebase::frontend/search', [
            'q' => $q,
            'articles' => $articles,
            'mailbox' => $mailbox,
        ]);
    }

    /**
     * Ajax controller.
     */
    public function ajaxHtml(Request $request)
    {
        switch ($request->action) {
            case 'reference':
                return view('knowledgebase::ajax_html/reference', [
                    
                ]);
        }

        abort(404);
    }

    public function customerLoginI18n(Request $request, $kb_locale, $mailbox_id = null)
    {
        return $this->customerLogin($request, $mailbox_id);
    }

    /**
     * Login.
     */
    public function customerLogin(Request $request, $mailbox_id = null)
    {
        $mailbox = $this->processMailboxId($mailbox_id);

        if (!$mailbox) {
            abort(404);
        }

        if (\Kb::authCustomer()) {
            return redirect()->route('knowledgebase.frontend.home', ['mailbox_id' => \Kb::encodeMailboxId($mailbox->id)]);
        }

        $redirect = $this->frontendProcessVisibility($mailbox);
        if ($redirect) {
            return $redirect;
        }

        return view('knowledgebase::customer_login', [
            'mailbox' => $mailbox,
        ]);
    }

    public function customerLoginProcessI18n(Request $request, $kb_locale, $mailbox_id = null)
    {
        return $this->customerLoginProcess($request, $mailbox_id);
    }

    /**
     * Process log in form.
     */
    public function customerLoginProcess(Request $request, $mailbox_id = null)
    {
        $result = [
            'result' => 'success',
            'message' => '',
        ];

        $mailbox = $this->processMailboxId($mailbox_id);

        if (!$mailbox) {
            abort(404);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|string',
        ]);

        $custom_errors = \Eventy::filter('knowledgebase.login.custom_check', [], $request);

        if ($validator->fails() || $custom_errors) {
            foreach ($custom_errors as $error_field => $error_message) {
                $validator->errors()->add($error_field, $error_message);
            }
            return redirect()->route('knowledgebase.login', ['mailbox_id' => \Kb::encodeMailboxId($mailbox->id)])
                        ->withErrors($validator)
                        ->withInput();
        }

        $email = \App\Email::sanitizeEmail($request->email);

        //$meta_settings = $mailbox->meta['kb'] ?? [];

        // Customer must exist in the DB in order to login.
        //if (!empty($meta_settings['existing'])) {
        $customer = Customer::getByEmail($email);

        if (!$customer) {
            $result['result'] = 'error';
            $result['message'] = __('There is no tickets belonging to the specified email address.');
        }
        // } else {

        //     $customer = Customer::create($email);
        //     if (!$customer) {
        //         $result['result'] = 'error';
        //         $result['message'] = __('Invalid Email Address');
        //     }
        // }

        // Send email to the customer.
        if (!$result['message']) {

            try {
                \MailHelper::setMailDriver($mailbox);

                $timestamp = time();

                $return_url = '';
                $referer = $request->headers->get('referer');
                if ($referer) {
                    $parts = parse_url($referer);
                    if (!empty($parts['query'])) {
                        parse_str($parts['query'], $query);
                        if (!empty($query['return_url'])) {

                            $parse_url = parse_url($query['return_url']);
                            if (!empty($parse_url['host'])
                                && (empty($mailbox->meta['kb']['domain']) || $mailbox->meta['kb']['domain'] == $parse_url['host'])
                            ) {
                                if (!empty($parse_url['path']) 
                                    && strstr($parse_url['path'], '/'.$mailbox_id)
                                ) {
                                    $return_url = $parse_url['path'];
                                    if (!empty($parse_url['query'])) {
                                        $return_url .= '?'.$parse_url['query'];
                                    }
                                }
                            }
                        }
                    }
                }

                \Mail::to([['email' => $request->email]])->send(new \Modules\KnowledgeBase\Mail\Login($mailbox, $customer, encrypt($timestamp), $return_url));

                $customer->setMeta('eup.auth_ts', $timestamp);
                $customer->save();

                \Session::put('eup.auth_ts', $timestamp);

                $result['message'] = __('Email with the authentication link has been sent to <strong>:email</strong>', ['email' => htmlspecialchars($request->email)]);
            } catch (\Exception $e) {
                // We come here in case SMTP server unavailable for example.
                // But Mail does not throw an exception if you specify incorrect SMTP details for example.
                \Helper::logException($e, '[Knowledge Base');
                $result['result'] = 'error';
                $result['message'] = __('Error occurred sending email to <strong>:email</strong>', ['email' => htmlspecialchars($request->email)]);
            }

            if (\Mail::failures()) {
                $result['result'] = 'error';
                $result['message'] = __('Error occurred sending email to <strong>:email</strong>', ['email' => htmlspecialchars($request->email)]);
            }
        }

        return view('knowledgebase::customer_login', [
            'mailbox' => $mailbox,
            'result' => $result,
        ]);
    }

    public function customerLoginFromEmailI18n(Request $request, $mailbox_id, $customer_id, $hash, $timestamp, $kb_locale)
    {
        return $this->customerLoginFromEmail($request, $mailbox_id, $customer_id, $hash, $timestamp);
    }

    /**
     * Login from email.
     */
    public function customerLoginFromEmail(Request $request, $mailbox_id, $customer_id, $hash, $timestamp)
    {
        $result = [
            'result' => 'error',
            'message' => __('Invalid authentication link'),
        ];

        $mailbox = $this->processMailboxId($mailbox_id);

        if (!$mailbox) {
            abort(404);
        }

        // Authenticate customer.
        $customer_id = \Helper::decrypt($customer_id);
        $timestamp = \Helper::decrypt($timestamp);

        try {
            $auth_redirect = \Kb::authenticate($customer_id, $mailbox->id, $hash, $timestamp, $request->url ?? '');

            if ($auth_redirect) {
                return $auth_redirect;
            }
        } catch (\Exception $e) {
            if ($e->getCode() == 300) {
                $result['message'] = $e->getMessage();
            }
        }

        return view('knowledgebase::customer_login', [
            'mailbox' => $mailbox,
            'result' => $result,
        ]);
    }

    public function customerLogoutI18n(Request $request, $mailbox_id, $kb_locale)
    {
        return $this->customerLogout($request, $mailbox_id);
    }

    /**
     * Logout.
     */
    public function customerLogout(Request $request, $mailbox_id)
    {
        $mailbox = $this->processMailboxId($mailbox_id);

        if (!$mailbox) {
            abort(404);
        }

        return redirect()->route('knowledgebase.frontend.home', ['mailbox_id' => \Kb::encodeMailboxId($mailbox->id)])
                        ->withCookie(cookie('enduserportal_auth', null, 0));
    }
}
