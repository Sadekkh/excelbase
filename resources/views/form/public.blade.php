@extends('layouts.auth')
@section('title', ($config['title'] ?? $table->name).' | Baserow')
@section('content')
<div class="public-form">
    <div class="public-form__cover" style="background: {{ $config['cover'] ?? '#5190ef' }}"></div>
    <div class="public-form__card">
        <div class="public-form__brand">
            @include('partials.logo', ['size' => 18])
            <span>Baserow</span>
        </div>
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
            <form method="post" action="{{ route('forms.public.submit', $view->public_slug) }}" class="public-form__fields">
                @csrf
                @foreach ($fields as $field)
                    <label class="field">
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
                <button type="submit" class="btn btn--primary btn--block">{{ $config['submit_text'] ?? 'Submit' }}</button>
            </form>
        @endif
    </div>
</div>
@endsection
