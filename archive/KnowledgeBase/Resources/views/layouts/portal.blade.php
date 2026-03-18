<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
	<head>
	    <meta charset="utf-8">
	    <meta http-equiv="X-UA-Compatible" content="IE=edge">
	    <meta name="viewport" content="width=device-width, initial-scale=1">

	    <!-- CSRF Token -->
	    <meta name="csrf-token" content="{{ csrf_token() }}">
	    {!! \Helper::cspMetaTag() !!}

	    <meta name="robots" content="noindex, nofollow">

	    <title>@if (View::getSection('title') != \Kb::getKbName($mailbox))@yield('title') - {{ \Kb::getKbName($mailbox) }}@else{{ \Kb::getKbName($mailbox) }}@endif</title>
	    
	    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
	    <link rel="shortcut icon" type="image/x-icon" href="@filter('layout.favicon', URL::asset('favicon.ico'))">
	    <link rel="manifest" href="{{ asset('site.webmanifest') }}" crossorigin="use-credentials">
	    <link rel="mask-icon" href="{{ asset('safari-pinned-tab.svg') }}" color="#5bbad5">
	    <meta name="msapplication-TileColor" content="#da532c">
	    <meta name="theme-color" content="@filter('layout.theme_color', '#ffffff')">
	    @action('layout.head')
	    @php
	        try {
	    @endphp
	    {!! Minify::stylesheet(\Eventy::filter('stylesheets', array('/css/fonts.css', '/css/bootstrap.css', '/css/style.css', \Module::getPublicPath(KB_MODULE).'/css/style.css'))) !!}
	    @php
	        } catch (\Exception $e) {
	            // Try...catch is needed to catch errors when activating a module and public symlink not created for module.
	            \Helper::logException($e);
	        }
	    @endphp
	    
	    @yield('stylesheets')
	</head>
    <body @yield('body_attrs')>
    	<div id="app">
	    	<nav class="navbar navbar-default navbar-static-top">
	            <div class="container">
	                <div class="navbar-header">

	                    <!-- Collapsed Hamburger -->
	                    <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#app-navbar-collapse" aria-expanded="false">
	                        <span class="sr-only">{{ __('Toggle Navigation') }}</span>
	                        <span class="icon-bar"></span>
	                        <span class="icon-bar"></span>
	                        <span class="icon-bar"></span>
	                    </button>
	                    <!-- Branding Image -->
	                    <a class="navbar-brand navbar-brand-with-text" href="{{ \Kb::getKbUrl($mailbox) }}">
	                    	@if (Eventy::filter('layout.header_logo', ''))
	                    		<img src="@filter('layout.header_logo', '')" height="100%" />
	                    	@endif
	                        <span>{{ $mailbox->name }}</span>
	                    </a>
	                </div>

	                <div class="collapse navbar-collapse" id="app-navbar-collapse">
	                    <!-- Left Side Of Navbar -->
	                    <ul class="nav navbar-nav navbar-right">
	                    	<li class="{{ \App\Misc\Helper::menuSelectedHtml('knowledgebase.frontend.home') }}"><a href="{{ \Kb::getKbUrl($mailbox) }}">{{ __('Home') }}</a></li>
	                    	@if (\Kb::getMenu($mailbox))
	                    		@foreach(\Kb::getMenu($mailbox) as $button_title => $button_url)
	                    			<li><a href="{{ $button_url }}">{{ $button_title }}</a></li>
	                    		@endforeach
	                    	@endif
	                    	@if (\Kb::isMultilingual($mailbox))
		                    	<li class="dropdown">
	                                <a href="#" class="dropdown-toggle dropdown-toggle-icon" data-toggle="dropdown" title="{{ __('Search') }}">
	                                    <i class="glyphicon glyphicon-globe"></i> <small class="kb-locale-name">{{ \Helper::getLocaleData(\Kb::getLocale())['name'] ?? '' }}</small>
	                                </a>

	                                <ul class="dropdown-menu">
			                    		@foreach(\Kb::getLocales($mailbox) as $locale)
			                    			<li @if ($locale == \Kb::getLocale()) class="active" @endif><a href="{{ \Kb::changeUrlLocale($locale) }}">{{ \Helper::getLocaleData($locale)['name'] ?? '' }}</a></li>
			                    		@endforeach
	                                </ul>
	                            </li>
	                    	@endif
	                    	@if (!in_array(\Kb::getSettings($mailbox)['visibility'], [\Kb::VISIBILITY_PUBLIC, \Kb::VISIBILITY_USERS]))
								@if (Kb::authCustomer())
									<li class="dropdown">

	                                    <a href="#" class="dropdown-toggle dropdown-toggle-icon dropdown-toggle-account" data-toggle="dropdown">
	                                    	<i class="glyphicon glyphicon-user"></i> <span class="nav-user">{{ Kb::authCustomer()->getMainEmail() }}</span> <span class="caret"></span>
	                                    </a>

	                                    <ul class="dropdown-menu">
	                                        <li>
	                                            <a href="#" id="kb-customer-logout-link">
	                                                {{ __('Log Out') }}
	                                            </a>

	                                            <form id="customer-logout-form" action="{{ route('knowledgebase.customer_logout', ['id' => \Kb::encodeMailboxId($mailbox->id)]) }}" method="POST" style="display: none;">
	                                                {{ csrf_field() }}
	                                            </form>
	                                        </li>
	                                    </ul>
	                                </li>
		                    	@endif
		                    @endif
		                    @if (\Kb::getSettings($mailbox)['visibility'] != \Kb::VISIBILITY_PUBLIC)
								@if (Auth::user())
									<li class="dropdown">
										<a href="#" class="dropdown-toggle dropdown-toggle-icon dropdown-toggle-account" data-toggle="dropdown" role="button" aria-expanded="false" aria-haspopup="true" v-pre title="{{ __('Account') }}" aria-label="{{ __('Account') }}">
	                                        <span class="photo-sm">@include('partials/person_photo', ['person' => Auth::user()])</span>&nbsp;<span class="nav-user">{{ Auth::user()->first_name }}@action('menu.user.name_append', Auth::user())</span> <span class="caret"></span>
	                                    </a>

	                                    <ul class="dropdown-menu">
	                                        <li>
	                                            <a href="#" id="kb-logout-link">
	                                                {{ __('Log Out') }}
	                                            </a>

	                                            <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
	                                                {{ csrf_field() }}
	                                            </form>
	                                        </li>
	                                    </ul>
	                                </li>
		                    	@endif
		                    @endif
                            <li class="dropdown">
                                <a href="#" class="dropdown-toggle dropdown-toggle-icon" data-toggle="dropdown" title="{{ __('Search') }}">
                                    <i class="glyphicon glyphicon-search"></i>
                                </a>

                                <ul class="dropdown-menu">
                                    <li>
                                        <form class="form-inline form-nav-search" role="form" action="{{ Kb::route('knowledgebase.frontend.search', ['mailbox_id'=>\Kb::encodeMailboxId($mailbox->id)], $mailbox) }}">
                                            <div class="input-group">
                                                <input type="text" class="form-control" name="q">
                                                <span class="input-group-btn">
                                                    <button class="btn btn-default" type="submit">{{ __('Search') }}</button>
                                                </span>
                                            </div>
                                        </form>
                                    </li>
                                </ul>
                            </li>
	                    </ul>
	                </div>
	            </div>
	        </nav>
	        <div class="content @yield('content_class')">
	        	<div id="kb-container">
	            	@yield('content')
	            </div>
	        </div>
			<div class="footer">
                {!! strtr(\Helper::stripDangerousTags($mailbox->meta['kb']['footer'] ?? '&copy; {%year%} {%mailbox.name%}'), ['{%year%}' => date('Y'), '{%mailbox.name%}' => $mailbox->name]) !!}
            </div>
	    </div>

	    @action('layout.body_bottom')

	    {{-- Scripts --}}
	    @php
	        try {
	    @endphp
	    {!! Minify::javascript(\Eventy::filter('kb.javascripts', ['/js/jquery.js', '/js/bootstrap.js', '/js/lang.js', '/storage/js/vars.js', '/js/laroute.js', '/js/parsley/parsley.min.js', '/js/parsley/i18n/'.strtolower(Config::get('app.locale')).'.js', \Module::getPublicPath(KB_MODULE).'/js/main.js', '/js/main.js'])) !!}
	    @php
	        } catch (\Exception $e) {
	            // To prevent 500 errors on update.
	            // Also catches errors when activating a module and public symlink not created for module.
	            if (strstr($e->getMessage(), 'vars.js')) {
	                \Artisan::call('freescout:generate-vars');
	            }
	            \Helper::logException($e);
	        }
	    @endphp
	    <script type="text/javascript" {!! \Helper::cspNonceAttr() !!}>
	        @yield('kb_javascript')
	    </script>
    </body>
</html>
