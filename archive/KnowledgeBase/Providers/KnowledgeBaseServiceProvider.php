<?php

namespace Modules\KnowledgeBase\Providers;

use App\Customer;
use App\Mailbox;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Factory;

// Module alias.
define('KB_MODULE', 'knowledgebase');

class KnowledgeBaseServiceProvider extends ServiceProvider
{
    // Subfolder in URL.
    const URL_SUBFOLDER = 'hc';

    const WIDGET_SALT = '2ke8gpeFd3';

    const AUTH_PERIOD = 43800; // month

    const AUTH_LINK_LIFETIME = 900; // seconds - 15 min

    // Use primary data if empty.
    public static $use_primary_if_empty = true;

    public static $mailboxes_ids = [];

    // Authenticated customer.
    public static $auth_customer = null;

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    // User permission.
    const PERM_EDIT_KB = 7;

    const VISIBILITY_PUBLIC = 1;
    const VISIBILITY_USERS = 2;
    const VISIBILITY_CUSTOMERS = 3;
    const VISIBILITY_USERS_CUSTOMERS = 4;

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerConfig();
        $this->registerViews();
        $this->registerFactories();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->hooks();
    }

    /**
     * Module hooks.
     */
    public function hooks()
    {
        // Add module's CSS file to the application layout.
        \Eventy::addFilter('stylesheets', function($styles) {
            $styles[] = \Module::getPublicPath(KB_MODULE).'/css/module.css';
            $styles[] = \Module::getPublicPath(KB_MODULE).'/css/bootstrap-colorpicker.css';
            return $styles;
        });

        // Add module's JS file to the application layout.
        \Eventy::addFilter('javascripts', function($javascripts) {
            $javascripts[] = \Module::getPublicPath(KB_MODULE).'/js/laroute.js';
            if (!preg_grep("/html5sortable\.js$/", $javascripts)) {
                $javascripts[] = '/js/html5sortable.js';
            }
            $javascripts[] = \Module::getPublicPath(KB_MODULE).'/js/bootstrap-colorpicker.js';
            $javascripts[] = \Module::getPublicPath(KB_MODULE).'/js/module.js';
            return $javascripts;
        });

        // \Eventy::addFilter('csp.script_src', function($value) {
        //     // Allow eval to preview the widget.
        //     if (\Route::is('mailboxes.knowledgebase.settings')) {
        //         $value .= " 'unsafe-eval'";
        //     }
        //     return $value;
        // });

        \Eventy::addAction('js.lang.messages', function() {
            ?>
                "kb_reference_title": "<?php echo __j("Insert Knowledge Base Reference") ?>",
            <?php
        });

        // Add item to the mailbox menu
        \Eventy::addAction('menu.append', function($mailbox) {
            if (\Kb::canEditKb()) {
                echo \View::make('knowledgebase::partials/menu', [])->render();
            }
        }, 1, 15);

        \Eventy::addFilter('menu.selected', function($menu) {
            if (\Kb::canEditKb()) {
                $menu['knowledgebase'] = [
                    'mailboxes.knowledgebase.settings',
                    'mailboxes.knowledgebase.categories',
                    'mailboxes.knowledgebase.articles',
                    'mailboxes.knowledgebase.article',
                    'mailboxes.knowledgebase.new_article',
                ];
            }
            if (self::isKb()) {
                $menu['knowledgebase.frontend.home'] = 'knowledgebase.frontend.home';
            }
            return $menu;
        });

        \Eventy::addFilter('user_permissions.list', function($list) {
            $list[] = \Kb::PERM_EDIT_KB;
            return $list;
        });

        \Eventy::addFilter('user_permissions.name', function($name, $permission) {
            if ($permission != \Kb::PERM_EDIT_KB) {
                return $name;
            }
            return __('Users are allowed to manage knowledge base');
        }, 20, 2);

        // Add item to the mailbox menu
        \Eventy::addAction('mailboxes.settings.menu', function($mailbox) {
            if (\Kb::canEditKb()) {
                echo \View::make('knowledgebase::partials/settings_menu', ['mailbox' => $mailbox])->render();
            }
        }, 37);

        // Replace host with custom domain if needed.
        \Eventy::addFilter('url_generator.app_url', function ($app_url) {
            // getHttpHost() includes port number.
            // https://github.com/freescout-helpdesk/freescout/issues/2736
            list($host) = explode(':', request()->getHttpHost());
            $app_url_host = parse_url($app_url, PHP_URL_HOST);

            if ($host != $app_url_host) {
                $app_url = str_replace($app_url_host, $host, $app_url);
            }

            return $app_url;
        });

        \Eventy::addFilter('middleware.web.custom_handle.response', function ($prev, $request, $next) {
            
            // If KB domain but URL is not KB - redirect to KB URL.
            list($host) = explode(':', request()->getHttpHost());

            if ($host != parse_url(config('app.url'), PHP_URL_HOST) && !self::isKb()) {

                // Get mailbox from URL.
                // $mailbox_id = self::getMailboxIdFromUrl(\Request::url());
                // if ($mailbox_id) {
                //     $mailbox = Mailbox::find($mailbox_id);
                //     if ($mailbox && !empty($mailbox->meta['kb']['domain']) && $mailbox->meta['kb']['domain'] == $host) {
                //         // Redirect to this mailbox's KB.
                //         return redirect(self::getKbUrl($mailbox));
                //     }
                // }

                // Try to find mailbox by host.
                $mailboxes = Mailbox::all();
                foreach ($mailboxes as $mailbox) {
                    if (!empty($mailbox->meta['kb']['domain'])) {
                        list($domain_host) = explode(':', $mailbox->meta['kb']['domain']);
                        if ($domain_host == $host) {
                            // If KB for the mail is visible to support agents only
                            // allow to view "login" page.
                            $visibility = $mailbox->meta['kb']['visibility'] ?? 0;
                            if (in_array($visibility, [\Kb::VISIBILITY_USERS, \Kb::VISIBILITY_CUSTOMERS, \Kb::VISIBILITY_USERS_CUSTOMERS])
                                && in_array($request->route()->getName(), ['login', 'password.request'])
                            ) {
                                break;
                            } else {
                                // Redirect to this mailbox's KB.
                                return redirect(self::getKbUrl($mailbox));
                            }
                        }
                    }
                }
            }

            return $prev;
        }, 10, 3);

        // \Eventy::addFilter('menu.selected', function($menu) {
        //     if (self::isEup()) {
        //         $menu['enduserportal.submit'] = 'enduserportal.submit';
        //         $menu['enduserportal.tickets'] = 'enduserportal.tickets';
        //     }

        //     return $menu;
        // });
    }

    public static function isKb()
    {
        return preg_match('/.*\/'.self::URL_SUBFOLDER.'\/.*/', \Request::url());
    }

    public static function getSettings($mailbox)
    {
        $meta_settings = $mailbox->meta['kb'] ?? [];

        $meta_settings['visibility'] = $meta_settings['visibility'] ?? \Kb::VISIBILITY_PUBLIC;

        return $meta_settings;
    }

    public static function getMailboxIdFromUrl($url)
    {
        $mailbox_id = null;

        preg_match('#.*/'.self::URL_SUBFOLDER.'/(\d+)#', $url, $m);

        if (!empty($m[1])) {
            $mailbox_id = self::decodeMailboxId($m[1]);
        }

        return $mailbox_id;
    }

    public static function insideWidgetUrl($decoded_mailbox_id, $params = [])
    {
        $url_params = array_merge(request()->all(), ['mailbox_id' => \Kb::encodeMailboxId($decoded_mailbox_id, \Kb::WIDGET_SALT)]);
        $url_params = array_merge($url_params, [
            'article_id' => '',
            'q' => '',
            'category_id' => '',
        ]);
        $url_params = array_merge($url_params, $params);
        return route('knowledgebase.widget_form', $url_params);
    }

    public static function canEditKb($user = null)
    {
        if (!$user) {
            $user = auth()->user();
        }
        if (!$user) {
            return false;
        }
        return $user->isAdmin() || $user->hasPermission(\Kb::PERM_EDIT_KB);
    }

    public static function getKbUrl($mailbox)
    {
        if (!$mailbox) {
            return '';
        }
        
        $url = \Kb::route('knowledgebase.frontend.home', ['mailbox_id' => \Kb::encodeMailboxId($mailbox->id)], $mailbox);

        if (!empty($mailbox->meta['kb']['domain'])) {
            $url = str_replace(parse_url($url, PHP_URL_HOST), $mailbox->meta['kb']['domain'], $url);
        }

        return $url;
    }

    public static function urlToFrontend($url, $mailbox)
    {
        if (!empty($mailbox->meta['kb']['domain'])) {
            $url = str_replace(parse_url($url, PHP_URL_HOST), $mailbox->meta['kb']['domain'], $url);
        }
        return $url;
    }

    public static function route($route, $params, $mailbox)
    {
        $locales = \Kb::getLocales($mailbox);
        if ($locales) {
            if (empty($params['kb_locale']) || !in_array($params['kb_locale'], $locales)) {
                $params['kb_locale'] = \Kb::getLocale() ?: \Kb::defaultLocale($mailbox);
            }
            $route .= '_i18n';
        } elseif (isset($params['kb_locale'])) {
            unset($params['kb_locale']);
        }
        return route($route, $params);
    }

    public static function changeUrlLocale($locale/*, $url = ''*/)
    {
        //if (!$url) {
        $url = \Request::getRequestUri();
        //}

        if (preg_match("#^/([^\/]+)/".self::URL_SUBFOLDER."/#", $url)) {
            return \Request::root().preg_replace("#^/([^\/]+)/".self::URL_SUBFOLDER."/#", '/'.$locale.($locale ? '/' : '').self::URL_SUBFOLDER.'/', $url);
        } else {
            // Add locale.
            return \Request::root().preg_replace("#^/".self::URL_SUBFOLDER."/#", '/'.$locale.'/'.self::URL_SUBFOLDER.'/', $url);
        }
    }

    public static function getKbName($mailbox)
    {
        if (!empty($mailbox->meta['kb']['site_name'])) {
            $name = $mailbox->meta['kb']['site_name'];
        } else {
            $name = __("{%mailbox.name%} Knowledge Base", ['{%mailbox.name%}' => $mailbox->name]);
        }

        return  __(str_replace("{%mailbox.name%}", ":{%mailbox.name%}", $name), ['{%mailbox.name%}' => $mailbox->name]);
    }

    /**
     * Returns a shorter value than encrypt().
     */
    public static function encodeMailboxId($id, $extra_salt = '')
    {
        // Use 'enduserportal' to have compatible IDs
        return crc32(config('app.key').'enduserportal'.$extra_salt.$id);
    }

    public static function decodeMailboxId($encoded_id, $extra_salt = '')
    {
        if (!empty(self::$mailboxes_ids[$encoded_id])) {
            return self::$mailboxes_ids[$encoded_id];
        }
        $cached_mailbox_id = \Cache::get('knowledgebase.decoded_mailbox_id_'.md5($encoded_id.$extra_salt));

        if ($cached_mailbox_id) {
            return $cached_mailbox_id;
        }

        $result = '';

        $mailboxes = Mailbox::get();

        foreach ($mailboxes as $mailbox) {
            $cur_encoded_id = self::encodeMailboxId($mailbox->id, $extra_salt);
            self::$mailboxes_ids[$cur_encoded_id] = $mailbox->id;

            if ($cur_encoded_id == $encoded_id) {
                $result = $mailbox->id;
            }
        }

        if ($result) {
            \Cache::put('knowledgebase.decoded_mailbox_id_'.md5($encoded_id.$extra_salt), $result, now()->addDays(1));
        }

        return $result;
    }

    public static function getWidgetScriptUrl($mailbox_id, $include_version = false)
    {
        $url = config('app.url').\Module::getPublicPath(KB_MODULE).'/js/widget.js';

        if ($include_version) {
            $module = \Module::findByAlias(KB_MODULE);
            if ($module) {
                $url .= '?v='.substr(crc32($module->get('version').config('app.key')), 0, 4);
            }
        }

        return $url;
    }

    public static function saveWidgetSettings($mailbox_id, $settings)
    {
        return \Option::set(KB_MODULE.'.widget_settings_'.$mailbox_id, $settings);
    }  

    public static function getWidgetSettings($mailbox_id)
    {
        return \Option::get(KB_MODULE.'.widget_settings_'.$mailbox_id, []);
    }

    public static function getDefaultWidgetSettings()
    {
        return [
            'id' => '',
            'color' => '#0068bd',
            'position' => 'br',
            'locale' => '',
        ];
    }

    public static function slugify($text)
    {
        return substr(\Str::slug($text, '-'), 0, 120);
    }

    public static function getMenu($mailbox)
    {
        $meta_settings = $mailbox->meta['kb'] ?? [];

        if (empty($meta_settings['menu'])) {
            return [];
        }

        preg_match_all("#\[([^\]]+)\]\(([^\)]+)\)#", $meta_settings['menu'], $m);

        if (empty($m[1])) {
            return [];
        }

        $menu = [];

        foreach ($m[1] as $i => $title) {
            $menu[$title] = $m[2][$i];
        }

        return $menu;
    }

    public static function getLocale()
    {
        // locale parameter is used in widget.
        return request()->locale ?: request()->kb_locale ?? '';
    }

    public static function getLocales($mailbox)
    {
        $meta_settings = $mailbox->meta['kb'] ?? [];
        return $meta_settings['locales'] ?? [];
    }

    public static function isMultilingual($mailbox)
    {
        return self::getLocales($mailbox);
    }

    public static function defaultLocale($mailbox)
    {
        $locales = self::getLocales($mailbox);

        return $locales[0] ?? '';
    }

    public static function backendLocale($mailbox)
    {
        $locales = self::getLocales($mailbox);

        if (request()->kb_locale && in_array(request()->kb_locale, $locales)) {
            return request()->kb_locale;
        }
        return $locales[0] ?? '';
    }

    public static function authenticate($customer_id, $mailbox_id, $hash, $timestamp, $return_url = '')
    {
        if (!is_numeric($timestamp) || time() - $timestamp > self::AUTH_LINK_LIFETIME) {
            throw new \Exception(__('Authentication link has expired'), 300);
        }

        $customer = Customer::find($customer_id);

        if ($customer->getMeta('eup.auth_ts') != $timestamp) {
            throw new \Exception(__('Authentication link has expired'), 300);
        }

        if (\Session::get('eup.auth_ts') != $timestamp) {
            throw new \Exception(__('Authentication link should be used in the same browser and device where it was requested'), 300);
        }

        if ($customer && $hash == self::customerHash($customer->created_at)) {
            $cookie = cookie('enduserportal_auth', encrypt($customer_id.'|'.$hash), self::AUTH_PERIOD);

            $customer->setMeta('eup.auth_ts', '');
            $customer->save();

            \Session::remove('eup.auth_ts');

            if ($return_url) {
                return redirect($return_url)->withCookie($cookie);
            } else {
                return redirect()->route('knowledgebase.frontend.home', ['mailbox_id' => \Kb::encodeMailboxId($mailbox_id)])
                                ->withCookie($cookie);
            }
        } else {
            return false;
        }
    }

    public static function customerHash($str)
    {
        return substr(md5($str), 0, 8);
    }

    /**
     * Get authenticated customer.
     */
    public static function authCustomer()
    {
        if (self::$auth_customer) {
            return self::$auth_customer;
        }

        $customer_id = null;
        $hash = null;
        $auth_data = request()->cookie('enduserportal_auth');

        if ($auth_data) {
            try {
                $auth_data_decrypted = decrypt($auth_data);
                list($customer_id, $hash) = explode('|', $auth_data_decrypted ?? '');
            } catch (\Exception $e) {
                return null;
            }
            if ($customer_id && $hash) {
                $customer = Customer::find($customer_id);
                if ($customer && self::customerHash($customer->created_at) == $hash) {
                    self::$auth_customer = $customer;
                }
            }
        }

        return self::$auth_customer;
    }

    public static function sanitizeColor($color)
    {
        return preg_replace("#;.*#", '', $color);
    }
    
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerTranslations();
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('knowledgebase.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php', 'knowledgebase'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/knowledgebase');

        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ],'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path . '/modules/knowledgebase';
        }, \Config::get('view.paths')), [$sourcePath]), 'knowledgebase');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $this->loadJsonTranslationsFrom(__DIR__ .'/../Resources/lang');
    }

    /**
     * Register an additional directory of factories.
     * @source https://github.com/sebastiaanluca/laravel-resource-flow/blob/develop/src/Modules/ModuleServiceProvider.php#L66
     */
    public function registerFactories()
    {
        if (! app()->environment('production')) {
            app(Factory::class)->load(__DIR__ . '/../Database/factories');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}
