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

use Redcodede\QrGen\Qr\Preset;

require __DIR__ . '/bootstrap.php';

// A development page that changes under the reader's feet. A cached copy showing
// yesterday's error is worse than a slightly slower reload.
header('Cache-Control: no-store, must-revalidate');

$texts = texts($_GET);
$input = readInput($_GET, $texts);

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

$png = null;

if ($matrix !== null) {
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

    form {
        display: grid;
        grid-template-columns: minmax(0, 3fr) minmax(0, 2fr) auto;
        gap: 14px;
        align-items: end;
        padding: 18px;
        background: var(--card);
        border: 1px solid var(--line);
        border-radius: 10px;
        margin-bottom: 12px;
    }

    @media (max-width: 640px) { form { grid-template-columns: 1fr; } }

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
        <div>
            <label for="url"><?= e($texts->get('form.url.label')) ?></label>
            <input type="text" id="url" name="url" value="<?= e($input['url']) ?>" spellcheck="false">
        </div>

        <div>
            <label for="logo"><?= e($texts->get('form.logo.label')) ?></label>
            <select id="logo" name="logo">
                <?php if ($logos === []): ?>
                    <option value=""><?= e($texts->get('form.logo.none')) ?></option>
                <?php endif; ?>
                <?php foreach ($logos as $file): ?>
                    <option value="<?= e($file) ?>"<?= $file === $input['logo'] ? ' selected' : '' ?>><?= e($file) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

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

    <div class="cols">
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
                    <a class="btn-link" href="<?= e(link_($query, ['download' => '1'])) ?>"><?= e($texts->get('panel.download.svg')) ?></a>
                    <a class="btn-link" href="<?= e(link_($query, ['format' => 'png', 'download' => '1'])) ?>"><?= e($texts->get('panel.download.png')) ?></a>
                    <a class="btn-link btn-secondary" href="<?= e(link_($query)) ?>" target="_blank" rel="noopener"><?= e($texts->get('panel.raw')) ?></a>
                </div>
            <?php else: ?>
                <p class="failure"><?= e($plainFailure ?? $texts->get('panel.nothing')) ?></p>
            <?php endif; ?>
        </div>

        <div class="panel">
            <h2><?= e($texts->get('panel.logo')) ?></h2>
            <?php if ($withLogo !== null && $input['logo'] !== ''): ?>
                <div class="preview"><?= $withLogo ?></div>
                <div class="actions">
                    <a class="btn-link" href="<?= e(link_($query, ['variant' => 'logo', 'download' => '1'])) ?>"><?= e($texts->get('panel.download.svg')) ?></a>
                    <a class="btn-link btn-secondary" href="<?= e(link_($query, ['variant' => 'logo'])) ?>" target="_blank" rel="noopener"><?= e($texts->get('panel.raw')) ?></a>
                </div>
                <p class="hint"><?= e($texts->get('panel.png.unavailable')) ?></p>
            <?php elseif ($input['logo'] === ''): ?>
                <p class="failure"><?= e($texts->get('panel.noLogo')) ?></p>
            <?php else: ?>
                <p class="failure"><?= e($logoFailure ?? $texts->get('panel.nothing')) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($matrix !== null): ?>
        <div class="panel facts">
            <h2><?= e($texts->get('facts.heading')) ?></h2>
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
