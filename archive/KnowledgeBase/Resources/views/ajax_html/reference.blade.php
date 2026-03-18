<div class="row-container">
    <form class="form-horizontal kb-reference-form">

        <div class="form-group">
            <label class="col-sm-3 control-label">{{ __('Text to Display') }}</label>
            <div class="col-sm-9">
                <input type="text" class="form-control kb-ref-text" name="text" required autofocus />
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{ __('Search') }}</label>
            <div class="col-sm-9">
                <div class="input-group">
                    <input type="text" class="form-control kb-ref-search-q">
                    <span class="input-group-btn">
                        <button class="btn btn-default kb-ref-search-button" type="button" data-loading-text="…"><i class="glyphicon glyphicon-search"></i></button>
                    </span>
                </div>
                <p class="form-help kb-ref-search-empty hidden">
                    {{ __('Nothing found') }}
                </p>
            </div>
        </div>

        <div class="form-group margin-bottom-10">
            <div class="kb-ref-table-container">
                
            </div>
        </div>

        <div class="form-group">
            <div class="col-sm-9 col-sm-offset-3">
                <button type="submit" class="btn btn-primary kb-ref-insert" data-loading-text="{{ __('Insert Reference') }}…" disabled="disabled">
                    {{ __('Insert Link') }}
                </button>
            </div>
        </div>

    </form>
</div>