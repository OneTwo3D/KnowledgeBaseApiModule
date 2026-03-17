@extends('layouts.app')

@section('title', __('Knowledge Base API'))

@section('content')
<div class="section-heading">
    {{ __('Knowledge Base API Settings') }} <span class="badge badge-info">v{{ $module_version }}</span>
    <span class="section-heading-right">
        <a href="{{ route('knowledgebaseapimodule.analytics') }}" class="btn btn-primary btn-sm">
            <i class="glyphicon glyphicon-stats"></i> {{ __('View Analytics') }}
        </a>
    </span>
</div>

<div class="container">
    <div class="row">
        <div class="col-xs-12 margin-top">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="glyphicon glyphicon-key"></i> {{ __('API Authentication') }}</h3>
                </div>
                <div class="panel-body">
                    <form class="form-horizontal" method="POST" action="{{ route('knowledgebaseapimodule.settings.save') }}">
                        {{ csrf_field() }}

                        <div class="form-group{{ $errors->has('api_token') ? ' has-error' : '' }}">
                            <label for="api_token" class="col-sm-2 control-label">{{ __('API Token') }}</label>

                            <div class="col-sm-8">
                                <div class="input-group">
                                    <input id="api_token" type="text" class="form-control" name="api_token" value="{{ old('api_token', $api_token) }}" maxlength="64" required autofocus>
                                    <span class="input-group-btn">
                                        <button class="btn btn-info generate-token" type="button">
                                            <i class="glyphicon glyphicon-refresh"></i> {{ __('Generate New Token') }}
                                        </button>
                                    </span>
                                </div>

                                @if ($errors->has('api_token'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('api_token') }}</strong>
                                    </span>
                                @endif
                                <p class="help-block">
                                    <i class="glyphicon glyphicon-info-sign"></i> {{ __('This token is required to authenticate API requests. Keep it secure!') }}
                                </p>
                            </div>
                        </div>

                        <div class="form-group{{ $errors->has('custom_url_template') ? ' has-error' : '' }}">
                            <label for="custom_url_template" class="col-sm-2 control-label">{{ __('Custom URL Template') }}</label>

                            <div class="col-sm-8">
                                <input id="custom_url_template" type="text" class="form-control" name="custom_url_template" value="{{ old('custom_url_template', $custom_url_template) }}" placeholder="https://example.com/docs/[mailbox]/[category]/[article]">

                                @if ($errors->has('custom_url_template'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('custom_url_template') }}</strong>
                                    </span>
                                @endif
                                <p class="help-block">
                                    <i class="glyphicon glyphicon-info-sign"></i> {{ __('Optional: Customize how article URLs are returned in API responses. Available placeholders:') }}
                                    <code>[mailbox]</code>, <code>[category]</code>, <code>[article]</code>
                                    <br>
                                    <small class="text-muted">{{ __('Example: https://your-app.com/api/docs/[category]/[article]') }}</small>
                                    <br>
                                    <small class="text-muted">{{ __('Leave empty to use default FreeScout knowledge base URLs.') }}</small>
                                </p>
                            </div>
                        </div>

                        <div class="form-group{{ $errors->has('client_url_template') ? ' has-error' : '' }}">
                            <label for="client_url_template" class="col-sm-2 control-label">{{ __('Client URL Template') }}</label>

                            <div class="col-sm-8">
                                <input id="client_url_template" type="text" class="form-control" name="client_url_template" value="{{ old('client_url_template', $client_url_template ?? '') }}" placeholder="https://ecomgraduates.com/pages/documentation?category=[category]&article=[article]">

                                @if ($errors->has('client_url_template'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('client_url_template') }}</strong>
                                    </span>
                                @endif
                                <p class="help-block">
                                    <i class="glyphicon glyphicon-info-sign"></i> {{ __('Optional: Add front-end client URLs to API responses. Available placeholders:') }}
                                    <code>[mailbox]</code>, <code>[category]</code>, <code>[article]</code>
                                    <br>
                                    <small class="text-muted">{{ __('Example: https://your-website.com/pages/documentation?category=[category]&article=[article]') }}</small>
                                    <br>
                                    <small class="text-muted">{{ __('This will add a "client_url" field to API responses for direct linking to your frontend.') }}</small>
                                </p>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="col-sm-8 col-sm-offset-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="glyphicon glyphicon-floppy-disk"></i> {{ __('Save') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="glyphicon glyphicon-book"></i> {{ __('API Documentation') }}</h3>
                </div>
                <div class="panel-body">

                    {{-- ── Endpoints ────────────────────────────────────────────────────── --}}
                    <h4>{{ __('Available Endpoints') }}</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>{{ __('Method') }}</th>
                                    <th>{{ __('Endpoint') }}</th>
                                    <th>{{ __('Description') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="label label-primary">GET</span></td>
                                    <td><code>/api/knowledgebase/{mailboxId}/categories</code></td>
                                    <td>
                                        {{ __('List all visible categories. Returns a flat list with') }} <code>parent_id</code> {{ __('by default.') }}
                                        {{ __('Pass') }} <code>?nested=true</code> {{ __('for a recursive tree with') }} <code>children</code> {{ __('arrays.') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td><span class="label label-primary">GET</span></td>
                                    <td><code>/api/knowledgebase/{mailboxId}/categories/{categoryId}</code></td>
                                    <td>{{ __('Get a single category with its articles and a') }} <code>subcategories</code> {{ __('array of direct children.') }}</td>
                                </tr>
                                <tr>
                                    <td><span class="label label-primary">GET</span></td>
                                    <td><code>/api/knowledgebase/{mailboxId}/categories/{categoryId}/articles/{articleId}</code></td>
                                    <td>{{ __('Get a single published article.') }}</td>
                                </tr>
                                <tr>
                                    <td><span class="label label-primary">GET</span></td>
                                    <td><code>/api/knowledgebase/{mailboxId}/search</code></td>
                                    <td>{{ __('Full-text search across published articles. Requires') }} <code>?q=</code>.</td>
                                </tr>
                                <tr>
                                    <td><span class="label label-primary">GET</span></td>
                                    <td><code>/api/knowledgebase/{mailboxId}/popular</code></td>
                                    <td>{{ __('Most-viewed categories and/or articles, ranked by view count.') }}</td>
                                </tr>
                                <tr>
                                    <td><span class="label label-primary">GET</span></td>
                                    <td><code>/api/knowledgebase/{mailboxId}/export</code></td>
                                    <td>
                                        {{ __('Export all KB content (flat by default).') }}
                                        {{ __('Pass') }} <code>?nested=true</code> {{ __('for hierarchical output with') }} <code>subcategories</code> {{ __('inside each category.') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- ── Query Parameters ─────────────────────────────────────────────── --}}
                    <h4>{{ __('Query Parameters') }}</h4>
                    <div class="well">
                        <dl class="dl-horizontal">
                            <dt><code>token</code> <span class="text-danger">*</span></dt>
                            <dd>{{ __('API token for authentication (required on every request).') }}</dd>

                            <dt><code>locale</code> / <code>lang</code></dt>
                            <dd>
                                {{ __('Language code for returned content (e.g.') }} <code>en</code>, <code>de</code>).
                                {{ __('Falls back to the mailbox default locale when omitted.') }}
                                {{ __('Both parameter names are accepted; ') }}<code>lang</code> {{ __('takes priority.') }}
                            </dd>

                            <dt><code>nested</code></dt>
                            <dd>
                                {{ __('Return a recursive category tree instead of a flat list.') }}
                                {{ __('Accepted values:') }} <code>true</code> / <code>1</code> {{ __('or') }} <code>false</code> / <code>0</code> ({{ __('default') }}).
                                <br>
                                <small class="text-muted">
                                    {{ __('Applies to') }} <code>/categories</code> {{ __('and') }} <code>/export</code>.
                                    {{ __('On') }} <code>/categories</code> {{ __('each node gains a') }} <code>children</code> {{ __('array; on') }} <code>/export</code> {{ __('each node gains a') }} <code>subcategories</code> {{ __('array.') }}
                                </small>
                                <br>
                                <small class="text-muted">
                                    {{ __('When omitted (flat mode), every category includes a') }} <code>parent_id</code> {{ __('field (') }}<code>null</code> {{ __('for root) so clients can build the tree themselves.') }}
                                </small>
                            </dd>

                            <dt><code>q</code></dt>
                            <dd>{{ __('Search keyword — required for') }} <code>/search</code>.</dd>

                            <dt><code>limit</code></dt>
                            <dd>{{ __('Max results returned by') }} <code>/popular</code> {{ __('(default: 5).') }}</dd>

                            <dt><code>type</code></dt>
                            <dd>{{ __('Filter for') }} <code>/popular</code>: <code>all</code> ({{ __('default') }}), <code>articles</code>, <code>categories</code>.</dd>

                            <dt><code>include_hidden</code></dt>
                            <dd>{{ __('Include hidden/draft content in') }} <code>/export</code>. {{ __('Default:') }} <code>false</code>.</dd>
                        </dl>
                    </div>

                    {{-- ── Response Shapes ──────────────────────────────────────────────── --}}
                    <h4>{{ __('Response Shapes') }}</h4>

                    <p><strong><code>GET /categories</code></strong> — {{ __('flat (default)') }}</p>
                    <pre>{
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
}</pre>

                    <p><strong><code>GET /categories?nested=true</code></strong> — {{ __('tree') }}</p>
                    <pre>{
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
}</pre>

                    <p><strong><code>GET /categories/{id}</code></strong></p>
                    <pre>{
  "mailbox_id": 1,
  "name": "My Mailbox",
  "category": {
    "id": 3,
    "name": "Getting Started",
    "description": "...",
    "url": "https://example.com/kb/category/3",
    "client_url": null,
    "subcategories": [
      { "id": 7, "name": "Installation", "article_count": 2, ... }
    ]
  },
  "articles": [
    { "id": 12, "title": "Quick start guide", "text": "...", "url": "...", "client_url": null }
  ]
}</pre>

                    <p><strong><code>GET /export?nested=true</code></strong> — {{ __('hierarchical export') }}</p>
                    <pre>{
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
      "articles": [ { "id": 12, "title": "...", "text": "...", "status": 1, ... } ],
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
}</pre>

                    {{-- ── Custom URL Templates ─────────────────────────────────────────── --}}
                    <h4>{{ __('Custom URL Templates') }}</h4>
                    <p>{{ __('Control how') }} <code>url</code> {{ __('and') }} <code>client_url</code> {{ __('fields are built in every response.') }}</p>
                    <div class="well">
                        <h5>{{ __('Placeholders') }}</h5>
                        <ul>
                            <li><code>[mailbox]</code> — {{ __('mailbox ID') }}</li>
                            <li><code>[category]</code> — {{ __('category ID') }}</li>
                            <li><code>[article]</code> — {{ __('article ID (omitted automatically for category URLs)') }}</li>
                        </ul>
                        <h5>{{ __('Example') }}</h5>
                        <p>{{ __('Client URL Template:') }} <code>https://your-site.com/docs?category=[category]&article=[article]</code></p>
                        <ul>
                            <li>{{ __('Article 10 in category 5 →') }} <code>https://your-site.com/docs?category=5&article=10</code></li>
                            <li>{{ __('Category 5 →') }} <code>https://your-site.com/docs?category=5</code></li>
                        </ul>
                        <div class="alert alert-info margin-top-10">
                            <i class="glyphicon glyphicon-info-sign"></i>
                            {{ __('Leave both templates empty to use the default FreeScout KB URLs.') }}
                        </div>
                    </div>

                    {{-- ── Example Requests ─────────────────────────────────────────────── --}}
                    <h4>{{ __('Example Requests') }}</h4>

                    <p>{{ __('Flat category list (default):') }}</p>
                    <pre>curl "{{ url('/api/knowledgebase/1/categories?token=' . ($api_token ?: 'YOUR_TOKEN')) }}"</pre>

                    <p>{{ __('Nested category tree:') }}</p>
                    <pre>curl "{{ url('/api/knowledgebase/1/categories?nested=true&token=' . ($api_token ?: 'YOUR_TOKEN')) }}"</pre>

                    <p>{{ __('Category with subcategories and articles:') }}</p>
                    <pre>curl "{{ url('/api/knowledgebase/1/categories/5?token=' . ($api_token ?: 'YOUR_TOKEN')) }}"</pre>

                    <p>{{ __('Single article:') }}</p>
                    <pre>curl "{{ url('/api/knowledgebase/1/categories/5/articles/10?token=' . ($api_token ?: 'YOUR_TOKEN')) }}"</pre>

                    <p>{{ __('Search:') }}</p>
                    <pre>curl "{{ url('/api/knowledgebase/1/search?q=installation&token=' . ($api_token ?: 'YOUR_TOKEN')) }}"</pre>

                    <p>{{ __('Popular (top 10 articles):') }}</p>
                    <pre>curl "{{ url('/api/knowledgebase/1/popular?type=articles&limit=10&token=' . ($api_token ?: 'YOUR_TOKEN')) }}"</pre>

                    <p>{{ __('Flat export with hidden content:') }}</p>
                    <pre>curl "{{ url('/api/knowledgebase/1/export?include_hidden=true&token=' . ($api_token ?: 'YOUR_TOKEN')) }}"</pre>

                    <p>{{ __('Nested export:') }}</p>
                    <pre>curl "{{ url('/api/knowledgebase/1/export?nested=true&token=' . ($api_token ?: 'YOUR_TOKEN')) }}"</pre>

                    <p>{{ __('Filter by locale (both forms accepted):') }}</p>
                    <pre>curl "{{ url('/api/knowledgebase/1/categories?lang=de&token=' . ($api_token ?: 'YOUR_TOKEN')) }}"</pre>

                    {{-- ── Try it out ────────────────────────────────────────────────────── --}}
                    <h4>{{ __('Try it out') }} <i class="glyphicon glyphicon-play-circle"></i></h4>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="api-test-endpoint">{{ __('Endpoint') }}</label>
                                <select id="api-test-endpoint" class="form-control">
                                    <option value="categories">{{ __('GET /categories — flat list') }}</option>
                                    <option value="categories-nested">{{ __('GET /categories?nested=true — tree') }}</option>
                                    <option value="category">{{ __('GET /categories/{id} — category + subcategories') }}</option>
                                    <option value="article">{{ __('GET /categories/{id}/articles/{id} — article') }}</option>
                                    <option value="search">{{ __('GET /search — full-text search') }}</option>
                                    <option value="popular">{{ __('GET /popular — most viewed') }}</option>
                                    <option value="export">{{ __('GET /export — flat export') }}</option>
                                    <option value="export-nested">{{ __('GET /export?nested=true — nested export') }}</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="api-test-mailbox">{{ __('Mailbox ID') }}</label>
                                <input type="number" id="api-test-mailbox" class="form-control" value="1" min="1">
                            </div>
                            <div class="form-group" id="category-id-container" style="display: none;">
                                <label for="api-test-category">{{ __('Category ID') }}</label>
                                <input type="number" id="api-test-category" class="form-control" value="1" min="1">
                            </div>
                            <div class="form-group" id="article-id-container" style="display: none;">
                                <label for="api-test-article">{{ __('Article ID') }}</label>
                                <input type="number" id="api-test-article" class="form-control" value="1" min="1">
                            </div>
                            <div class="form-group" id="search-keyword-container" style="display: none;">
                                <label for="api-test-keyword">{{ __('Search Keyword') }}</label>
                                <input type="text" id="api-test-keyword" class="form-control" placeholder="{{ __('Enter search term...') }}">
                            </div>
                            <div class="form-group" id="popular-limit-container" style="display: none;">
                                <label for="api-test-limit">{{ __('Results Limit') }}</label>
                                <input type="number" id="api-test-limit" class="form-control" value="5" min="1" max="50">
                            </div>
                            <div class="form-group" id="popular-type-container" style="display: none;">
                                <label for="api-test-type">{{ __('Content Type') }}</label>
                                <select id="api-test-type" class="form-control">
                                    <option value="all">{{ __('All (categories & articles)') }}</option>
                                    <option value="articles">{{ __('Articles only') }}</option>
                                    <option value="categories">{{ __('Categories only') }}</option>
                                </select>
                            </div>
                            <button type="button" id="api-test-button" class="btn btn-success" {{ empty($api_token) ? 'disabled' : '' }}>
                                <i class="glyphicon glyphicon-send"></i> {{ __('Send Request') }}
                            </button>
                            @if(empty($api_token))
                            <p class="text-danger margin-top">
                                <i class="glyphicon glyphicon-warning-sign"></i> {{ __('Please set and save an API token above to enable testing.') }}
                            </p>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <div class="panel panel-default">
                                <div class="panel-heading">
                                    <h4 class="panel-title">{{ __('Response') }}</h4>
                                </div>
                                <div class="panel-body">
                                    <pre id="api-response" style="max-height: 400px; overflow: auto; font-size: 12px;">{{ __('Response will appear here...') }}</pre>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript" {!! \Helper::cspNonceAttr() !!}>
    document.addEventListener('DOMContentLoaded', function() {
        // Token generator code
        const generateButton = document.querySelector('.generate-token');
        
        generateButton.addEventListener('click', function() {
            let token = '';
            const possible = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            
            for (let i = 0; i < 32; i++) {
                token += possible.charAt(Math.floor(Math.random() * possible.length));
            }
            
            document.getElementById('api_token').value = token;
        });

        // API testing functionality
        const endpointSelect = document.getElementById('api-test-endpoint');
        const categoryContainer = document.getElementById('category-id-container');
        const testButton = document.getElementById('api-test-button');
        const responseElement = document.getElementById('api-response');
        
        // Show/hide fields based on selected endpoint
        endpointSelect.addEventListener('change', function() {
            categoryContainer.style.display = 'none';
            document.getElementById('article-id-container').style.display = 'none';
            document.getElementById('search-keyword-container').style.display = 'none';
            document.getElementById('popular-limit-container').style.display = 'none';
            document.getElementById('popular-type-container').style.display = 'none';

            if (this.value === 'category') {
                categoryContainer.style.display = 'block';
            } else if (this.value === 'article') {
                categoryContainer.style.display = 'block';
                document.getElementById('article-id-container').style.display = 'block';
            } else if (this.value === 'search') {
                document.getElementById('search-keyword-container').style.display = 'block';
            } else if (this.value === 'popular') {
                document.getElementById('popular-limit-container').style.display = 'block';
                document.getElementById('popular-type-container').style.display = 'block';
            }
        });

        // Handle API test request
        testButton.addEventListener('click', function() {
            const endpoint = endpointSelect.value;
            const mailboxId = document.getElementById('api-test-mailbox').value;
            const base = '{{ url("/api/knowledgebase/") }}/' + mailboxId;
            const token = 'token={{ $api_token }}';
            let url = '';

            if (endpoint === 'categories') {
                url = base + '/categories?' + token;
            } else if (endpoint === 'categories-nested') {
                url = base + '/categories?nested=true&' + token;
            } else if (endpoint === 'category') {
                const categoryId = document.getElementById('api-test-category').value;
                url = base + '/categories/' + categoryId + '?' + token;
            } else if (endpoint === 'article') {
                const categoryId = document.getElementById('api-test-category').value;
                const articleId = document.getElementById('api-test-article').value;
                url = base + '/categories/' + categoryId + '/articles/' + articleId + '?' + token;
            } else if (endpoint === 'search') {
                const keyword = document.getElementById('api-test-keyword').value;
                if (!keyword) {
                    responseElement.textContent = 'Error: Search keyword is required';
                    return;
                }
                url = base + '/search?q=' + encodeURIComponent(keyword) + '&' + token;
            } else if (endpoint === 'popular') {
                const limit = document.getElementById('api-test-limit').value;
                const type  = document.getElementById('api-test-type').value;
                url = base + '/popular?limit=' + encodeURIComponent(limit) + '&type=' + encodeURIComponent(type) + '&' + token;
            } else if (endpoint === 'export') {
                url = base + '/export?' + token;
            } else if (endpoint === 'export-nested') {
                url = base + '/export?nested=true&' + token;
            }
            
            // Show loading message
            responseElement.textContent = 'Loading...';
            
            // Make the API call
            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP error ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    // Format and display the JSON response
                    responseElement.textContent = JSON.stringify(data, null, 2);
                })
                .catch(error => {
                    responseElement.textContent = 'Error: ' + error.message;
                });
        });
    });
</script>
@endsection 