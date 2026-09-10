<?php

/**
 * Demo page: type a URL, see the symbol, download the file.
 *
 * Deliberately plain PHP with no framework. Everything on this page runs through
 * the same framework-free classes the Statamic addon will call later, so if it
 * works here it works there — and if it stops working here, the fault is in the
 * package rather than in the glue.
 */

declare(strict_types=1);

namespace Redcodede\QrGen\Demo;

use Redcodede\QrGen\Qr\ErrorCorrection;

require __DIR__ . '/bootstrap.php';

$input = readInput($_GET);
$matrix = null;
$svg = null;
$failure = null;

if ($input['errors'] === []) {
    [$svg, $failure] = tryRender($input, false);

    if ($svg !== null) {
        $matrix = encode($input);
    }
}

$query = [
    'url' => $input['url'],
    'level' => $input['level'],
    'moduleSize' => $input['moduleSize'],
    'quietZone' => $input['quietZone'],
];

if ($input['transparent']) {
    $query['transparent'] = '1';
}

$svgUrl = 'svg.php?' . http_build_query($query);
$downloadUrl = 'svg.php?' . http_build_query($query + ['download' => '1']);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>qr-gen demo</title>
<style>
    :root {
        --bg: #fbfaf9;
        --fg: #1a1a1a;
        --muted: #6b6b6b;
        --line: #dcd8d4;
        --card: #ffffff;
        --accent: #b4342c;
    }

    @media (prefers-color-scheme: dark) {
        :root {
            --bg: #17181a;
            --fg: #e8e6e3;
            --muted: #9a9793;
            --line: #33353a;
            --card: #1f2124;
            --accent: #e8635a;
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

    main { max-width: 900px; margin: 0 auto; }

    h1 { font-size: 20px; margin: 0 0 4px; letter-spacing: -0.01em; }
    h2 { font-size: 13px; text-transform: uppercase; letter-spacing: 0.07em; color: var(--muted); margin: 0 0 12px; }

    .lede { color: var(--muted); margin: 0 0 28px; }

    form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 14px;
        align-items: end;
        padding: 18px;
        background: var(--card);
        border: 1px solid var(--line);
        border-radius: 10px;
        margin-bottom: 24px;
    }

    .field-wide { grid-column: 1 / -1; }

    label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 5px; }

    input[type="text"], input[type="number"], select {
        width: 100%;
        padding: 8px 10px;
        font: inherit;
        font-size: 14px;
        color: var(--fg);
        background: var(--bg);
        border: 1px solid var(--line);
        border-radius: 6px;
    }

    .check { display: flex; align-items: center; gap: 8px; }
    .check label { margin: 0; }

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

    .cols { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 24px; }
    @media (max-width: 640px) { .cols { grid-template-columns: 1fr; } }

    .panel {
        padding: 18px;
        background: var(--card);
        border: 1px solid var(--line);
        border-radius: 10px;
    }

    .preview {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background-image:
            linear-gradient(45deg, var(--line) 25%, transparent 25%, transparent 75%, var(--line) 75%),
            linear-gradient(45deg, var(--line) 25%, transparent 25%, transparent 75%, var(--line) 75%);
        background-size: 16px 16px;
        background-position: 0 0, 8px 8px;
        border-radius: 8px;
    }

    .preview svg { max-width: 100%; height: auto; }

    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th, td { text-align: left; padding: 6px 0; border-bottom: 1px solid var(--line); vertical-align: top; }
    th { font-weight: 500; color: var(--muted); width: 45%; }
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

    .errors {
        padding: 14px 18px;
        margin-bottom: 24px;
        background: var(--card);
        border: 1px solid var(--accent);
        border-left-width: 3px;
        border-radius: 8px;
    }

    .errors ul { margin: 0; padding-left: 18px; }

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
    <h1>qr-gen</h1>
    <p class="lede">
        URL in, SVG out. Nothing is written to disk &mdash; every request encodes and
        renders from scratch, and the download regenerates rather than fetching a
        stored file.
    </p>

    <form method="get" action="index.php">
        <div class="field-wide">
            <label for="url">URL</label>
            <input type="text" id="url" name="url" value="<?= e($input['url']) ?>" spellcheck="false">
        </div>

        <div>
            <label for="level">Error correction</label>
            <select id="level" name="level">
                <?php foreach (ErrorCorrection::all() as $level): ?>
                    <option value="<?= e($level) ?>"<?= $level === $input['level'] ? ' selected' : '' ?>>
                        <?= e($level) ?><?= e(levelHint($level)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="moduleSize">Module size (px)</label>
            <input type="number" id="moduleSize" name="moduleSize" min="<?= MIN_MODULE_SIZE ?>" max="<?= MAX_MODULE_SIZE ?>" value="<?= $input['moduleSize'] ?>">
        </div>

        <div>
            <label for="quietZone">Quiet zone (modules)</label>
            <input type="number" id="quietZone" name="quietZone" min="0" max="<?= MAX_QUIET_ZONE ?>" value="<?= $input['quietZone'] ?>">
        </div>

        <div class="check">
            <input type="checkbox" id="transparent" name="transparent" value="1"<?= $input['transparent'] ? ' checked' : '' ?>>
            <label for="transparent">Transparent background</label>
        </div>

        <div><button type="submit">Generate</button></div>
    </form>

    <?php if ($input['errors'] !== [] || $failure !== null): ?>
        <div class="errors">
            <ul>
                <?php foreach ($input['errors'] as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
                <?php if ($failure !== null): ?>
                    <li><?= e($failure) ?></li>
                <?php endif; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($svg !== null && $matrix !== null): ?>
        <div class="cols">
            <div class="panel">
                <h2>Preview</h2>
                <div class="preview"><?= $svg ?></div>
                <div class="actions">
                    <a class="btn-link" href="<?= e($downloadUrl) ?>">Download SVG</a>
                    <a class="btn-link btn-secondary" href="<?= e($svgUrl) ?>" target="_blank" rel="noopener">Open raw</a>
                </div>
            </div>

            <div class="panel">
                <h2>What came out</h2>
                <table>
                    <tr><th>Payload</th><td><?= strlen($input['url']) ?> bytes</td></tr>
                    <tr><th>Error correction</th><td><?= e($input['level']) ?><?= e(levelHint($input['level'])) ?></td></tr>
                    <tr><th>QR version</th><td><?= versionOf($matrix) ?> of 40</td></tr>
                    <tr><th>Modules</th><td><?= $matrix->size() ?> &times; <?= $matrix->size() ?></td></tr>
                    <tr><th>With quiet zone</th><td><?= $matrix->size() + 2 * $input['quietZone'] ?> &times; <?= $matrix->size() + 2 * $input['quietZone'] ?> modules</td></tr>
                    <tr><th>Rendered size</th><td><?= ($matrix->size() + 2 * $input['quietZone']) * $input['moduleSize'] ?> px square</td></tr>
                    <tr><th>SVG</th><td><?= number_format(strlen($svg)) ?> bytes, one <code>&lt;path&gt;</code></td></tr>
                </table>
            </div>
        </div>

        <details>
            <summary>SVG source</summary>
            <pre><?= e($svg) ?></pre>
        </details>
    <?php endif; ?>

    <footer>
        No logo yet &mdash; that needs a decision on print size and an RGB version of the
        logo first. A symbol with a logo has to be proofed at final size on the real
        material before it can be promised.
    </footer>
</main>
</body>
</html>
