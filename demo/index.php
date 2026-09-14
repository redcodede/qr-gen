<?php

/**
 * Demo page: type a URL, pick artwork, see both symbols, download either.
 *
 * Deliberately plain PHP with no framework. Everything on this page runs through
 * the same framework-free classes the Statamic addon will call later, so if it
 * works here it works there — and if it stops working here, the fault is in the
 * package rather than in the glue.
 *
 * No text is written in this file. Every string comes from the catalogue in
 * resources/lang, German by default, so the page and the eventual plugin share
 * one set of texts instead of drifting apart.
 */

declare(strict_types=1);

namespace Redcodede\QrGen\Demo;

use Redcodede\QrGen\Qr\Exception\QrGenException;
use Redcodede\QrGen\Qr\Logo\PngLogo;
use Redcodede\QrGen\Qr\Preset;
use Redcodede\QrGen\Qr\Settings\Variant;

require __DIR__ . '/bootstrap.php';

// A development page that changes under the reader's feet. A cached copy showing
// yesterday's error is worse than a slightly slower reload.
header('Cache-Control: no-store, must-revalidate');

$texts = texts($_GET);
$input = readInput($_GET, $texts);

// Die zwei Konfigurationsebenen und ihr Ergebnis. Alles Weitere auf dieser
// Seite rechnet mit den aufgelösten Werten in $input['url'] und $input['logo']
// und muss von den Ebenen nichts wissen.
$global = $input['global'];
$page = $input['page'];
$effective = $input['effective'];

$matrix = null;
$plain = null;
$plainFailure = null;
$withLogo = null;
$logoFailure = null;

if ($input['errors'] === []) {
    [$plain, $plainFailure] = tryRender($input['url'], $input['logo'], false, false);
    [$withLogo, $logoFailure] = tryRender($input['url'], $input['logo'], false, true);

    if ($plain !== null) {
        $matrix = encode($input['url']);
    }
}

$logos = availableLogos();
[$fitResult, $fitFailure] = fit($input['url']);
$level = effectiveLevel($input['url']);

// The resolver's report is the better message: it says what happened at every
// level, not just at the one that happened to be tried.
if ($fitFailure !== null) {
    $logoFailure = $fitFailure;
    $withLogo = null;
}

$query = ['url' => $input['url'], 'logo' => $input['logo']];

if ($texts->locale() !== 'de') {
    $query['lang'] = $texts->locale();
}

$box = Preset::LOGO_BOX_MODULES;
$margin = Preset::LOGO_MARGIN_MODULES;
$cleared = $box * $box;
$drawable = $box - (2 * $margin);

// Whichever format is fewer bytes gets shown. For a two-colour QR code that is
// usually the PNG, which is not the intuition a vector format invites.
[$previewFormat, $pngBytes, $svgBytes] = $matrix !== null
    ? cheaperFormat($input['url'], $input['logo'], false)
    : ['svg', 0, 0];

// Asked once, up here, because it decides both the download button and the
// figures next to it.
$logoPngRejection = pngRejection($input['logo'], true);
$logoPngBytes = null;

if ($matrix !== null && $input['logo'] !== '' && $logoPngRejection === null) {
    [$rendered] = tryRender($input['url'], $input['logo'], false, true, 'png');
    $logoPngBytes = $rendered === null ? null : strlen($rendered);
}

// What the printed size asks of raster artwork, and what was supplied. A
// vector logo does not care; a PNG below this figure is being enlarged.
$artworkPixels = null;
$logoPixels = null;

$png = null;

if ($matrix !== null) {
    $artworkPixels = pngRenderer($input['logo'], true)->artworkPixels($matrix);

    try {
        $artwork = logo($input['logo']);

        if ($artwork instanceof PngLogo) {
            $logoPixels = [$artwork->pixelWidth(), $artwork->pixelHeight()];
        }
    } catch (QrGenException $exception) {
        // Already reported next to the artwork panel.
    }

    $pngRenderer = pngRenderer();
    $png = [
        'pixels' => $pngRenderer->pixelWidth($matrix),
        'perModule' => $pngRenderer->pixelsPerModule($matrix),
        'printed' => $pngRenderer->printedSizeMm($matrix),
    ];
}

function link_(array $query, array $extra = []): string
{
    return 'image.php?' . http_build_query(array_merge($query, $extra));
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

?><!doctype html>
<html lang="<?= e($texts->locale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($texts->get('app.title')) ?></title>
<style>
    :root {
        --bg: #fbfaf9;
        --fg: #1a1a1a;
        --muted: #6b6b6b;
        --line: #dcd8d4;
        --card: #ffffff;
        --accent: #b4342c;
        --warn: #8a5a00;
    }

    @media (prefers-color-scheme: dark) {
        :root {
            --bg: #17181a;
            --fg: #e8e6e3;
            --muted: #9a9793;
            --line: #33353a;
            --card: #1f2124;
            --accent: #e8635a;
            --warn: #d9a53a;
        }
    }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        padding: 32px 16px 64px;
        background: var(--bg);
        color: var(--fg);
        font: 15px/1.55 ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
    }

    main { max-width: 1000px; margin: 0 auto; }

    h1 { font-size: 20px; margin: 0 0 4px; letter-spacing: -0.01em; }
    h2 { font-size: 13px; text-transform: uppercase; letter-spacing: 0.07em; color: var(--muted); margin: 0 0 12px; }

    .lede { color: var(--muted); margin: 0 0 28px; }

    form { margin: 0; }

    /* Drei Bereiche, drei Farben. Die Seite muss nicht aussehen wie Statamic,
       aber sie muss auf einen Blick zeigen, wohin eine Einstellung gehört: was
       global gilt, was die Seite bestimmt, und was dabei herauskommt. */
    .group {
        margin-bottom: 22px;
        background: var(--card);
        border: 1px solid var(--line);
        border-left: 3px solid var(--group);
        border-radius: 10px;
    }

    .group > header { padding: 13px 18px; border-bottom: 1px solid var(--line); }
    .group > header h2 { margin: 0; color: var(--group); }
    .group > header p { margin: 3px 0 0; font-size: 13px; color: var(--muted); }

    .group-body { padding: 18px; }

    .group-global { --group: #3a6ea5; }
    .group-page { --group: #4a7c59; }
    .group-output { --group: var(--accent); }
    .group-facts { --group: var(--line); }
    .group-facts > header h2 { color: var(--muted); }

    @media (prefers-color-scheme: dark) {
        .group-global { --group: #7aa6d6; }
        .group-page { --group: #7fb490; }
    }

    .fields { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 16px; }
    @media (max-width: 700px) { .fields { grid-template-columns: 1fr; } }

    .switches { display: flex; flex-wrap: wrap; gap: 8px 16px; padding-top: 4px; }

    .switch { display: flex; align-items: center; gap: 7px; font-size: 14px; }
    .switch input { margin: 0; }
    .switch.disabled { color: var(--muted); }

    .resolution {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 8px 22px;
        margin-bottom: 18px;
        font-size: 13px;
    }

    @media (max-width: 700px) { .resolution { grid-template-columns: 1fr; } }

    .resolution > div { display: flex; gap: 8px; min-width: 0; }
    .resolution dt { flex: none; color: var(--muted); margin: 0; }
    .resolution dd { margin: 0; min-width: 0; overflow-wrap: anywhere; }
    .resolution .tag { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; }
    .tag-page { color: #4a7c59; }
    .tag-global { color: #3a6ea5; }
    .tag-nowhere { color: var(--accent); }

    @media (prefers-color-scheme: dark) {
        .tag-page { color: #7fb490; }
        .tag-global { color: #7aa6d6; }
    }

    label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 5px; }

    input[type="text"], select {
        width: 100%;
        padding: 8px 10px;
        font: inherit;
        font-size: 14px;
        color: var(--fg);
        background: var(--bg);
        border: 1px solid var(--line);
        border-radius: 6px;
    }

    button {
        padding: 9px 16px;
        font: inherit;
        font-weight: 600;
        color: #fff;
        background: var(--accent);
        border: 0;
        border-radius: 6px;
        cursor: pointer;
    }

    .buttons { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }

    .fixed {
        padding: 12px 18px;
        margin-bottom: 24px;
        font-size: 13px;
        color: var(--muted);
        background: var(--card);
        border: 1px solid var(--line);
        border-radius: 10px;
    }

    .fixed strong { color: var(--fg); font-weight: 600; }

    .cols { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 24px; }
    @media (max-width: 700px) { .cols { grid-template-columns: 1fr; } }

    .panel {
        display: flex;
        flex-direction: column;
        padding: 18px;
        background: var(--card);
        border: 1px solid var(--line);
        border-radius: 10px;
    }

    .preview {
        display: flex;
        align-items: center;
        justify-content: center;
        flex: 1;
        padding: 20px;
        background-image:
            linear-gradient(45deg, var(--line) 25%, transparent 25%, transparent 75%, var(--line) 75%),
            linear-gradient(45deg, var(--line) 25%, transparent 25%, transparent 75%, var(--line) 75%);
        background-size: 16px 16px;
        background-position: 0 0, 8px 8px;
        border-radius: 8px;
    }

    .preview svg { max-width: 100%; height: auto; }

    /* A 1184-pixel raster shown at a third of that: without this the browser
       smooths module edges into grey and the preview looks softer than the
       file is. */
    .preview img { max-width: 100%; height: auto; image-rendering: pixelated; }

    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th, td { text-align: left; padding: 6px 0; border-bottom: 1px solid var(--line); vertical-align: top; }
    th { font-weight: 500; color: var(--muted); width: 55%; }
    tr:last-child th, tr:last-child td { border-bottom: 0; }

    .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }

    .btn-link {
        display: inline-block;
        padding: 9px 16px;
        font-size: 14px;
        font-weight: 600;
        text-decoration: none;
        color: #fff;
        background: var(--accent);
        border-radius: 6px;
    }

    .btn-secondary { color: var(--fg); background: transparent; border: 1px solid var(--line); }

    .errors, .note {
        padding: 14px 18px;
        margin-bottom: 24px;
        background: var(--card);
        border: 1px solid var(--accent);
        border-left-width: 3px;
        border-radius: 8px;
    }

    .note { border-color: var(--warn); font-size: 14px; }
    .note-print { border-color: var(--line); color: var(--muted); }
    .errors ul { margin: 0; padding-left: 18px; }
    .failure { color: var(--accent); font-size: 14px; margin: 0; }
    .hint { margin: 14px 0 0; font-size: 13px; color: var(--muted); }

    .facts { margin-top: 24px; }
    details { margin-top: 24px; }
    summary { cursor: pointer; font-size: 13px; color: var(--muted); }

    pre {
        overflow-x: auto;
        padding: 14px;
        margin: 12px 0 0;
        font-size: 12px;
        background: var(--card);
        border: 1px solid var(--line);
        border-radius: 8px;
    }

    footer { margin-top: 40px; font-size: 13px; color: var(--muted); }
    code { font-size: 0.92em; }
</style>
</head>
<body>
<main>
    <h1><?= e($texts->get('app.title')) ?></h1>
    <p class="lede"><?= e($texts->get('app.subtitle')) ?></p>

    <?php /*
        autocomplete="off" is the important attribute here, not a nicety.
        Chrome restores form field values on a soft reload, so a value that got
        into a field once survives every refresh — the URL and the defaults say
        one thing and the form shows another.
    */ ?>
    <form method="get" action="index.php" autocomplete="off">
        <?php /*
            Ein nicht angehaktes Kästchen schickt gar nichts. Ohne diese Marke
            liesse sich "abgewählt" nicht von "zum ersten Mal geöffnet"
            unterscheiden, und nichts liesse sich je abschalten.
        */ ?>
        <input type="hidden" name="configured" value="1">
        <?php if ($texts->locale() !== 'de'): ?>
            <input type="hidden" name="lang" value="<?= e($texts->locale()) ?>">
        <?php endif; ?>

        <section class="group group-global">
            <header>
                <h2><?= e($texts->get('group.global.heading')) ?></h2>
                <p><?= e($texts->get('group.global.note')) ?></p>
            </header>
            <div class="group-body">
                <div class="fields">
                    <div>
                        <label><?= e($texts->get('form.variants.label')) ?></label>
                        <div class="switches">
                            <label class="switch">
                                <input type="checkbox" name="g[variants][plain]" value="1"<?= $global->offersPlain() ? ' checked' : '' ?>>
                                <?= e($texts->get('panel.plain')) ?>
                            </label>
                            <label class="switch">
                                <input type="checkbox" name="g[variants][logo]" value="1"<?= $global->offersLogo() ? ' checked' : '' ?>>
                                <?= e($texts->get('panel.logo')) ?>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label><?= e($texts->get('form.downloads.label')) ?></label>
                        <div class="switches">
                            <label class="switch">
                                <input type="checkbox" name="g[downloads][svg]" value="1"<?= $global->offersSvg() ? ' checked' : '' ?>>
                                SVG
                            </label>
                            <label class="switch">
                                <input type="checkbox" name="g[downloads][png]" value="1"<?= $global->offersPng() ? ' checked' : '' ?>>
                                PNG
                            </label>
                        </div>
                    </div>

                    <div>
                        <label for="g-url"><?= e($texts->get('form.defaultUrl.label')) ?></label>
                        <input type="text" id="g-url" name="g[url]" value="<?= e($global->defaultUrl()) ?>" spellcheck="false">
                    </div>

                    <div>
                        <label for="g-logo"><?= e($texts->get('form.defaultLogo.label')) ?></label>
                        <select id="g-logo" name="g[logo]">
                            <option value=""><?= e($texts->get('form.logo.none')) ?></option>
                            <?php foreach ($logos as $file): ?>
                                <option value="<?= e($file) ?>"<?= $file === $global->defaultLogo() ? ' selected' : '' ?>><?= e($file) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </section>

        <section class="group group-page">
            <header>
                <h2><?= e($texts->get('group.page.heading')) ?></h2>
                <p><?= e($texts->get('group.page.note')) ?></p>
            </header>
            <div class="group-body">
                <div class="fields">
                    <div>
                        <label for="p-url"><?= e($texts->get('form.pageUrl.label')) ?></label>
                        <input type="text" id="p-url" name="p[url]" value="<?= e($page->url()) ?>"
                               placeholder="<?= e($global->defaultUrl() ?? $texts->get('form.inherit.empty')) ?>" spellcheck="false">
                    </div>

                    <div>
                        <label for="p-logo"><?= e($texts->get('form.pageLogo.label')) ?></label>
                        <select id="p-logo" name="p[logo]">
                            <option value=""><?= e($texts->get('form.inherit', [
                                'value' => $global->defaultLogo() ?? $texts->get('form.logo.none'),
                            ])) ?></option>
                            <?php foreach ($logos as $file): ?>
                                <option value="<?= e($file) ?>"<?= $file === $page->logo() ? ' selected' : '' ?>><?= e($file) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label><?= e($texts->get('form.pageVariants.label')) ?></label>
                        <div class="switches">
                            <?php foreach ([Variant::PLAIN => 'panel.plain', Variant::LOGO => 'panel.logo'] as $variant => $key): ?>
                                <?php $offered = $variant === Variant::PLAIN ? $global->offersPlain() : $global->offersLogo(); ?>
                                <label class="switch<?= $offered ? '' : ' disabled' ?>"
                                       title="<?= $offered ? '' : e($texts->get('form.pageVariants.blocked')) ?>">
                                    <input type="checkbox" name="p[variants][]" value="<?= e($variant) ?>"
                                           <?= $page->wants($variant) ? ' checked' : '' ?><?= $offered ? '' : ' disabled' ?>>
                                    <?= e($texts->get($key)) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="buttons">
            <button type="submit"><?= e($texts->get('form.submit')) ?></button>
            <a class="btn-link btn-secondary" href="index.php"><?= e($texts->get('form.reset')) ?></a>
        </div>
    </form>

    <p class="fixed">
        <strong><?= e($texts->get('form.fixed.heading')) ?>:</strong>
        <?= e($texts->get('form.fixed.note', [
            'box' => Preset::LOGO_BOX_MODULES,
            'margin' => Preset::LOGO_MARGIN_MODULES,
            'moduleSize' => Preset::MODULE_SIZE,
            'quietZone' => Preset::QUIET_ZONE,
        ])) ?>
    </p>

    <?php if ($input['errors'] !== []): ?>
        <div class="errors">
            <ul>
                <?php foreach ($input['errors'] as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (Preset::QUIET_ZONE < 4): ?>
        <div class="note"><?= e($texts->get('notice.quietZone', ['quietZone' => Preset::QUIET_ZONE])) ?></div>
    <?php endif; ?>

    <div class="note note-print"><?= e($texts->get('notice.print', [
        'dark' => Preset::DARK_COLOR,
        'light' => Preset::LIGHT_COLOR,
    ])) ?></div>

    <section class="group group-output">
        <header>
            <h2><?= e($texts->get('group.output.heading')) ?></h2>
            <p><?= e($texts->get('group.output.note')) ?></p>
        </header>
        <div class="group-body">

    <?php /*
        Woher der Wert kommt, ist keine Bequemlichkeit: wer eine unerwartete URL
        vor sich hat, muss ohne Suchen erkennen, ob sie aus der Seite oder aus
        den globalen Einstellungen stammt.
    */ ?>
    <dl class="resolution">
        <div>
            <dt><?= e($texts->get('resolution.url')) ?></dt>
            <dd>
                <code><?= e($effective->url() ?? $texts->get('resolution.none')) ?></code>
                <span class="tag tag-<?= e($effective->urlSource()) ?>"><?= e($texts->get('resolution.from.' . $effective->urlSource())) ?></span>
            </dd>
        </div>
        <div>
            <dt><?= e($texts->get('resolution.logo')) ?></dt>
            <dd>
                <code><?= e($effective->logo() ?? $texts->get('resolution.none')) ?></code>
                <span class="tag tag-<?= e($effective->logoSource()) ?>"><?= e($texts->get('resolution.from.' . $effective->logoSource())) ?></span>
            </dd>
        </div>
    </dl>

    <?php if (!$effective->showsAnything()): ?>
        <p class="failure"><?= e($texts->get('output.nothing')) ?></p>
    <?php endif; ?>

    <?php if (!$global->offersAnyDownload()): ?>
        <p class="hint"><?= e($texts->get('output.noDownloads')) ?></p>
    <?php endif; ?>

    <div class="cols">
        <?php if ($effective->showsPlain()): ?>
        <div class="panel">
            <h2><?= e($texts->get('panel.plain')) ?></h2>
            <?php if ($plain !== null): ?>
                <div class="preview">
                    <?php if ($previewFormat === 'png'): ?>
                        <img src="<?= e(link_($query, ['format' => 'png'])) ?>" alt="" width="<?= (int) $png['pixels'] ?>" height="<?= (int) $png['pixels'] ?>">
                    <?php else: ?>
                        <?= $plain ?>
                    <?php endif; ?>
                </div>
                <div class="actions">
                    <?php if ($effective->offersSvg()): ?>
                        <a class="btn-link" href="<?= e(link_($query, ['download' => '1'])) ?>"><?= e($texts->get('panel.download.svg')) ?></a>
                    <?php endif; ?>
                    <?php if ($effective->offersPng()): ?>
                        <a class="btn-link" href="<?= e(link_($query, ['format' => 'png', 'download' => '1'])) ?>"><?= e($texts->get('panel.download.png')) ?></a>
                    <?php endif; ?>
                    <a class="btn-link btn-secondary" href="<?= e(link_($query)) ?>" target="_blank" rel="noopener"><?= e($texts->get('panel.raw')) ?></a>
                </div>
            <?php else: ?>
                <p class="failure"><?= e($plainFailure ?? $texts->get('panel.nothing')) ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($effective->showsLogo()): ?>
        <div class="panel">
            <h2><?= e($texts->get('panel.logo')) ?></h2>
            <?php if ($withLogo !== null && $input['logo'] !== ''): ?>
                <div class="preview"><?= $withLogo ?></div>
                <div class="actions">
                    <?php if ($effective->offersSvg()): ?>
                        <a class="btn-link" href="<?= e(link_($query, ['variant' => 'logo', 'download' => '1'])) ?>"><?= e($texts->get('panel.download.svg')) ?></a>
                    <?php endif; ?>
                    <?php if ($effective->offersPng() && $logoPngRejection === null): ?>
                        <a class="btn-link" href="<?= e(link_($query, ['variant' => 'logo', 'format' => 'png', 'download' => '1'])) ?>"><?= e($texts->get('panel.download.png')) ?></a>
                    <?php endif; ?>
                    <a class="btn-link btn-secondary" href="<?= e(link_($query, ['variant' => 'logo'])) ?>" target="_blank" rel="noopener"><?= e($texts->get('panel.raw')) ?></a>
                </div>
                <p class="hint"><?= e($logoPngRejection === null
                    ? $texts->get('panel.png.whichFormat')
                    : $texts->get('panel.png.refused', ['reason' => $logoPngRejection])) ?></p>
            <?php elseif ($input['logo'] === ''): ?>
                <p class="failure"><?= e($texts->get('panel.noLogo')) ?></p>
            <?php else: ?>
                <p class="failure"><?= e($logoFailure ?? $texts->get('panel.nothing')) ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

        </div>
    </section>

    <?php if ($matrix !== null): ?>
        <section class="group group-facts">
            <header><h2><?= e($texts->get('facts.heading')) ?></h2></header>
            <div class="group-body">
            <div class="cols">
                <table>
                    <tr>
                        <th><?= e($texts->get('facts.payload')) ?></th>
                        <td><?= e($texts->get('facts.payload.value', ['bytes' => strlen($input['url'])])) ?></td>
                    </tr>
                    <tr>
                        <th><?= e($texts->get('facts.level')) ?></th>
                        <td>
                            <?= e($texts->get('level.' . $level->value())) ?><br>
                            <small><?= e($texts->get('facts.level.auto')) ?></small>
                        </td>
                    </tr>
                    <tr>
                        <th><?= e($texts->get('facts.version')) ?></th>
                        <td><?= e($texts->get('facts.version.value', ['version' => $matrix->version()])) ?></td>
                    </tr>
                    <tr>
                        <th><?= e($texts->get('facts.modules')) ?></th>
                        <td><?= e($texts->get('facts.modules.value', [
                            'size' => $matrix->size(),
                            'total' => number_format($matrix->size() ** 2, 0, ',', '.'),
                        ])) ?></td>
                    </tr>
                    <?php if ($fitResult !== null): ?>
                        <tr>
                            <th><?= e($texts->get('facts.allowance')) ?></th>
                            <td><?= e($texts->get('facts.allowance.value', [
                                'used' => number_format($fitResult->clearedShare() * 100, 1, ',', '.'),
                                'budget' => number_format($fitResult->budget() * 100, 1, ',', '.'),
                                'headroom' => number_format($fitResult->headroom() * 100, 0, ',', '.'),
                            ])) ?></td>
                        </tr>
                        <tr>
                            <th><?= e($texts->get('facts.alignment')) ?></th>
                            <td><?= e($fitResult->compromisesAlignment()
                                ? $texts->get('facts.alignment.given', ['modules' => $fitResult->placement()->coveredAlignmentModules()])
                                : $texts->get('facts.alignment.intact')) ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
                <table>
                    <tr>
                        <th><?= e($texts->get('facts.box')) ?></th>
                        <td><?= e($texts->get('facts.box.value', ['box' => $box])) ?></td>
                    </tr>
                    <tr>
                        <th><?= e($texts->get('facts.cleared')) ?></th>
                        <td><?= e($texts->get('facts.cleared.value', [
                            'modules' => $cleared,
                            'share' => number_format($cleared / ($matrix->size() ** 2) * 100, 1, ',', '.'),
                        ])) ?></td>
                    </tr>
                    <tr>
                        <th><?= e($texts->get('facts.margin')) ?></th>
                        <td><?= e($texts->get('facts.margin.value', [
                            'margin' => $margin,
                            'drawable' => $drawable,
                        ])) ?></td>
                    </tr>
                    <tr>
                        <th><?= e($texts->get('facts.logoWidth')) ?></th>
                        <td><?= e($texts->get('facts.logoWidth.value', [
                            'percent' => number_format($box / $matrix->size() * 100, 0, ',', '.'),
                        ])) ?></td>
                    </tr>
                    <tr>
                        <th><?= e($texts->get('facts.largestBox')) ?></th>
                        <td><?= e($texts->get('facts.largestBox.value', [
                            'modules' => \Redcodede\QrGen\Qr\Logo\LogoBox::largestSideFor($matrix->size()),
                        ])) ?></td>
                    </tr>
                    <tr>
                        <th><?= e($texts->get('facts.svgSize')) ?></th>
                        <td><?= e($texts->get('facts.svgSize.value', [
                            'plain' => number_format(strlen((string) $plain), 0, ',', '.'),
                            'logo' => number_format(strlen((string) $withLogo), 0, ',', '.'),
                        ])) ?></td>
                    </tr>
                    <?php if ($png !== null): ?>
                        <tr>
                            <th><?= e($texts->get('facts.png')) ?></th>
                            <td><?= e($texts->get('facts.png.value', [
                                'pixels' => number_format($png['pixels'], 0, ',', '.'),
                                'perModule' => $png['perModule'],
                                'bytes' => number_format($pngBytes, 0, ',', '.'),
                            ])) ?></td>
                        </tr>
                        <?php if ($artworkPixels !== null): ?>
                            <tr>
                                <th><?= e($texts->get('facts.artworkArea')) ?></th>
                                <td>
                                    <?= e($texts->get('facts.artworkArea.value', [
                                        'width' => $artworkPixels[0],
                                        'height' => $artworkPixels[1],
                                    ])) ?>
                                    <?php if ($logoPixels !== null): ?>
                                        <br>
                                        <small><?= e($texts->get(
                                            $logoPixels[0] >= $artworkPixels[0] && $logoPixels[1] >= $artworkPixels[1]
                                                ? 'facts.artworkArea.enough'
                                                : 'facts.artworkArea.short',
                                            ['width' => $logoPixels[0], 'height' => $logoPixels[1]]
                                        )) ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($logoPngBytes !== null): ?>
                            <tr>
                                <th><?= e($texts->get('facts.pngLogo')) ?></th>
                                <td><?= e($texts->get('facts.pngLogo.value', [
                                    'bytes' => number_format($logoPngBytes, 0, ',', '.'),
                                ])) ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <th><?= e($texts->get('facts.printSize')) ?></th>
                            <td><?= e($texts->get('facts.printSize.value', [
                                'size' => number_format($png['printed'], 2, ',', '.'),
                                'dpi' => Preset::PRINT_DPI,
                                'ordered' => number_format(Preset::PRINT_SIZE_MM, 0, ',', '.'),
                            ])) ?></td>
                        </tr>
                        <tr>
                            <th><?= e($texts->get('facts.preview')) ?></th>
                            <td><?= e($texts->get('facts.preview.value', [
                                'format' => strtoupper($previewFormat),
                            ])) ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
            </div>
        </section>

        <?php if ($withLogo !== null): ?>
            <details>
                <summary><?= e($texts->get('source.summary')) ?></summary>
                <pre><?= e((string) $withLogo) ?></pre>
            </details>
        <?php endif; ?>
    <?php endif; ?>

    <footer><?= e($texts->get('footer')) ?></footer>
</main>
</body>
</html>
