@extends('layouts.auth')
@section('title', ($config['title'] ?? $table->name).' | Baserow')
@section('content')
<div class="public-form">
    <div class="public-form__cover" style="background: {{ $config['cover'] ?? '#5190ef' }}"></div>
    <div class="public-form__card">
        @if (empty($config['hide_branding']))
        <div class="public-form__brand">
            @include('partials.logo', ['size' => 18])
            <span>Baserow</span>
        </div>
        @endif
        <h1>{{ $config['title'] ?? $table->name }}</h1>
        @if (!empty($config['description']))
            <p class="public-form__desc">{{ $config['description'] }}</p>
        @endif

        @if (session('submitted'))
            <div class="public-form__success">
                @include('partials.icon', ['name' => 'check', 'size' => 20])
                <p>{{ $config['success_message'] ?? 'Thank you for submitting!' }}</p>
            </div>
        @else
            <form method="post" action="{{ route('forms.public.submit', $view->public_slug) }}" class="public-form__fields {{ ($config['mode'] ?? 'form') === 'survey' ? 'is-survey' : '' }}" id="public-form">
                @csrf
                @foreach ($fields as $index => $field)
                    <label class="field survey-step" data-step="{{ $index }}">
                        <span>{{ $field->name }}</span>
                        @if ($field->type === 'long_text')
                            <textarea name="values[{{ $field->id }}]" rows="4"></textarea>
                        @elseif ($field->type === 'boolean')
                            <span class="check"><input type="checkbox" name="values[{{ $field->id }}]" value="1"> Yes</span>
                        @elseif ($field->type === 'date')
                            <input type="{{ ($field->options['include_time'] ?? false) ? 'datetime-local' : 'date' }}" name="values[{{ $field->id }}]">
                        @elseif ($field->type === 'number' || $field->type === 'rating')
                            <input type="number" name="values[{{ $field->id }}]" @if($field->type==='rating') min="0" max="{{ $field->options['max'] ?? 5 }}" @endif>
                        @elseif ($field->type === 'single_select')
                            <select name="values[{{ $field->id }}]">
                                <option value="">—</option>
                                @foreach ($field->options['options'] ?? [] as $opt)
                                    <option value="{{ $opt['id'] }}">{{ $opt['value'] }}</option>
                                @endforeach
                            </select>
                        @elseif ($field->type === 'multiple_select')
                            <div class="check-list">
                                @foreach ($field->options['options'] ?? [] as $opt)
                                    <label class="check"><input type="checkbox" name="values[{{ $field->id }}][]" value="{{ $opt['id'] }}"> {{ $opt['value'] }}</label>
                                @endforeach
                            </div>
                        @elseif ($field->type === 'email')
                            <input type="email" name="values[{{ $field->id }}]">
                        @elseif ($field->type === 'url')
                            <input type="url" name="values[{{ $field->id }}]" placeholder="https://">
                        @elseif ($field->type === 'phone')
                            <input type="tel" name="values[{{ $field->id }}]">
                        @else
                            <input type="text" name="values[{{ $field->id }}]">
                        @endif
                    </label>
                @endforeach
                @if (($config['mode'] ?? 'form') === 'survey' && $fields->count() > 1)
                    <div class="survey-nav">
                        <button type="button" class="btn btn--ghost" id="survey-prev">Back</button>
                        <span id="survey-pos">1 / {{ $fields->count() }}</span>
                        <button type="button" class="btn btn--primary" id="survey-next">Next</button>
                        <button type="submit" class="btn btn--primary" id="survey-submit" hidden>{{ $config['submit_text'] ?? 'Submit' }}</button>
                    </div>
                @else
                    <button type="submit" class="btn btn--primary btn--block">{{ $config['submit_text'] ?? 'Submit' }}</button>
                @endif
            </form>
        @endif
    </div>
</div>
@endsection
@push('scripts')
@if (($config['mode'] ?? 'form') === 'survey')
<script>
(() => {
  const steps = [...document.querySelectorAll('.survey-step')];
  if (steps.length < 2) return;
  let i = 0;
  const prev = document.getElementById('survey-prev');
  const next = document.getElementById('survey-next');
  const submit = document.getElementById('survey-submit');
  const pos = document.getElementById('survey-pos');
  function paint() {
    steps.forEach((el, idx) => { el.hidden = idx !== i; });
    prev.hidden = i === 0;
    next.hidden = i === steps.length - 1;
    submit.hidden = i !== steps.length - 1;
    pos.textContent = (i + 1) + ' / ' + steps.length;
  }
  prev.onclick = () => { i = Math.max(0, i - 1); paint(); };
  next.onclick = () => { i = Math.min(steps.length - 1, i + 1); paint(); };
  paint();
})();
</script>
@endif
@endpush
