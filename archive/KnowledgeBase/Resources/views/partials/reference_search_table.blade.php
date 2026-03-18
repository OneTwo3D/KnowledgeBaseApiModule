<table class="table table-striped table-narrow">
    @foreach ($items as $item)
        <tr>
            <td class="kb-ref-table-radio"><input type="radio" name="kb_ref_search_item" value="1" class="kb-ref-search-item" required/></td>
            <td>
                <a href="{{ $item['url'] }}" target="_blank">{{ $item['title'] }}</a>
                @if ($item['locale']) <span class="text-warning">({{ $item['locale'] }})</span>@endif
            </td>
        </tr>
    @endforeach
</table>