{{--
    Die Einstellungsseite der Erweiterung.

    Kein eigenes JavaScript und kein Build im Paket: `publish-form` ist
    Statamics eigene Komponente und bringt Speichern, Fehlerbehandlung, Toast
    und Strg+S mit. Dieselbe Bauweise benutzt Statamic für seine Globals.

    Die festen Vorgaben stehen darunter als Text und nicht als
    schreibgeschütztes Feld. Ein Feld sieht aus, als ginge es doch.
--}}

@extends('statamic::layout')
@section('title', $title)

@section('content')

    <publish-form
        title="{{ $title }}"
        action="{{ $action }}"
        method="patch"
        :blueprint='@json($blueprint)'
        :meta='@json($meta)'
        :values='@json($values)'
    ></publish-form>

    <div class="max-w-lg mt-4 text-sm text-grey-70">{{ $intro }}</div>

    <div class="card p-0 mt-6">
        <div class="p-4 border-b">
            <h2>{{ __('qr-gen::texts.cp.section.fixed') }}</h2>
            <p class="text-sm text-grey-70 mt-1">{{ __('qr-gen::texts.cp.fixed.hint') }}</p>
        </div>

        <table class="data-table">
            <tbody>
                @foreach ($fixed as $zeile)
                    <tr>
                        <td class="w-64 font-medium">{{ $zeile['label'] }}</td>
                        <td>
                            {{ $zeile['value'] }}
                            @if ($zeile['hint'])
                                <div class="text-2xs text-grey-70 mt-1">{{ $zeile['hint'] }}</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

@endsection
