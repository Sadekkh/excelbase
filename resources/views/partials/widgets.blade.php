<div class="widget-grid">
    @forelse ($widgets as $item)
        @php($widget = $item['model'])
        @php($data = $item['data'])
        <article class="widget widget--{{ $widget->type }}">
            <header>
                <h3>{{ $widget->title }}</h3>
                @if (!empty($canBuild) && !empty($dashboard))
                    <form method="post" action="{{ route('dashboards.widgets.destroy', [$workspace, $dashboard, $widget]) }}" onsubmit="return confirm('Remove this widget?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="icon-btn" title="Remove">@include('partials.icon', ['name' => 'x', 'size' => 14])</button>
                    </form>
                @endif
            </header>
            <p class="widget__value">{{ $data['value'] }}</p>
            <p class="widget__detail">{{ $data['detail'] }}</p>
            @if (!empty($data['bars']))
                @php($max = max(1, ...array_column($data['bars'], 'value')))
                <div class="widget__bars">
                    @foreach ($data['bars'] as $bar)
                        <div>
                            <span>{{ $bar['label'] }}</span>
                            <i style="width: {{ round(($bar['value'] / $max) * 100) }}%; background: {{ $bar['color'] }}"></i>
                            <em>{{ $bar['value'] }}</em>
                        </div>
                    @endforeach
                </div>
            @endif
            @if (!empty($data['rows']))
                <ul class="widget__list">
                    @foreach ($data['rows'] as $row)
                        <li><a href="{{ $row['href'] }}">{{ $row['label'] }}</a></li>
                    @endforeach
                </ul>
            @endif
        </article>
    @empty
        <div class="empty-state">
            <h2>No widgets yet</h2>
            <p>A builder can pin counts, charts, and recent rows from any table.</p>
        </div>
    @endforelse
</div>
