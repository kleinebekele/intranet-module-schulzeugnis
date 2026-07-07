@php $val = fn ($k) => $daten[$k] ?? ''; @endphp
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "DejaVu Sans", sans-serif; color: #1f2937; }
        .sheet { position: relative; background: #fff; overflow: hidden; page-break-after: always; }
        .sheet:last-child { page-break-after: auto; }
        .panel { position: absolute; overflow: hidden; }
        .el { position: absolute; line-height: 1.35; }
        .sig { border-top: 0.3mm solid #374151; padding-top: 1mm; }
        .fach { margin-bottom: 2.5mm; }
        .fach b { display: block; }
        .falz { position: absolute; top: 0; bottom: 0; border-left: 0.2mm dashed #cbd5e1; }
    </style>
</head>
<body>
@foreach ($seiten as $sheet)
    <div class="sheet" style="width: {{ $sheet['b'] }}mm; height: {{ $sheet['h'] }}mm;">
        @foreach ($sheet['panels'] as $panel)
            <div class="panel" style="left: {{ $panel['x'] }}mm; top: {{ $panel['y'] }}mm; width: {{ $panel['w'] }}mm; height: {{ $panel['h'] }}mm;">
                @foreach ($panel['elemente'] as $el)
                    @php
                        $x = $el['x']; $y = $el['y']; $w = $el['w']; $h = $el['h'];
                        $size = $el['size'] ?? 11;
                        $align = $el['align'] ?? 'left';
                        $weight = ($el['bold'] ?? false) ? 'bold' : 'normal';
                        $style = "left:{$x}mm;top:{$y}mm;width:{$w}mm;height:{$h}mm;font-size:{$size}pt;text-align:{$align};font-weight:{$weight};";
                    @endphp

                    @if ($el['typ'] === 'text')
                        <div class="el" style="{{ $style }}">{{ $el['text'] ?? '' }}</div>
                    @elseif ($el['typ'] === 'feld')
                        <div class="el" style="{{ $style }}">{{ $val($el['bindung'] ?? '') }}</div>
                    @elseif ($el['typ'] === 'block' && ($el['bindung'] ?? '') === 'fachtexte')
                        <div class="el" style="{{ $style }}">
                            @foreach ($val('fachtexte') as $f)
                                <div class="fach"><b>{{ $f['fach'] }}</b>{{ $f['text'] }}</div>
                            @endforeach
                        </div>
                    @elseif ($el['typ'] === 'block')
                        <div class="el" style="{{ $style }}white-space: pre-line;">{{ $val($el['bindung'] ?? '') }}</div>
                    @elseif ($el['typ'] === 'unterschrift')
                        <div class="el sig" style="{{ $style }}">{{ $val($el['bindung'] ?? '') ?: ($el['text'] ?? '') }}</div>
                    @elseif ($el['typ'] === 'bild')
                        @if (! empty($el['src']))
                            <img style="position:absolute;left:{{ $x }}mm;top:{{ $y }}mm;width:{{ $w }}mm;height:{{ $h }}mm;object-fit:contain;" src="{{ $el['src'] }}">
                        @endif
                    @elseif ($el['typ'] === 'linie')
                        <div class="el" style="left:{{ $x }}mm;top:{{ $y }}mm;width:{{ $w }}mm;height:0;border-top:{{ $el['staerke'] ?? 0.3 }}mm {{ $el['stil'] ?? 'solid' }} #374151;"></div>
                    @endif
                @endforeach
            </div>
        @endforeach

        @if (count($sheet['panels']) > 1)
            <div class="falz" style="left: {{ $sheet['b'] / 2 }}mm;"></div>
        @endif
    </div>
@endforeach
</body>
</html>
