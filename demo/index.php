<?php

/**
 * Demo page: type a URL, see both symbols, download either.
 *
 * Deliberately plain PHP with no framework. Everything on this page runs through
 * the same framework-free classes the Statamic addon will call later, so if it
 * works here it works there — and if it stops working here, the fault is in the
 * package rather than in the glue.
 */

declare(strict_types=1);

namespace Redcodede\QrGen\Demo;

use Redcodede\QrGen\Qr\ErrorCorrection;
use Redcodede\QrGen\Qr\Logo\LogoBox;

require __DIR__ . '/bootstrap.php';

// A development page that changes under the reader's feet. A cached copy showing
// yesterday's error is worse than a slightly slower reload.
header('Cache-Control: no-store, must-revalidate');

$input = readInput($_GET);
$matrix = null;
$plain = null;
$plainFailure = null;
$withLogo = null;
$logoFailure = null;

if ($input['errors'] === []) {
    [$plain, $plainFailure] = tryRender($input, false, false);
    [$withLogo, $logoFailure] = tryRender($input, false, true);

    if ($plain !== null) {
        $matrix = encode($input);
    }
}

$logos = availableLogos();
$isAuto = $input['level'] === LEVEL_AUTO;
[$fitResult, $fitFailure] = $isAuto ? fit($input) : [null, null];
$level = effectiveLevel($input);

// On "auto" the resolver's report is the better message: it says what happened
// at every level, not just at the one that happened to be tried.
if ($isAuto && $fitFailure !== null) {
    $logoFailure = $fitFailure;
    $withLogo = null;
}

$query = [
    'url' => $input['url'],
    'level' => $input['level'],
    'moduleSize' => $input['moduleSize'],
    'quietZone' => $input['quietZone'],
    'logo' => $input['logo'],
    'logoModules' => $input['logoModules'],
    'logoMargin' => $input['logoMargin'],
];

if ($input['transparent']) {
    $query['transparent'] = '1';
}

if ($input['allowAlignment']) {
    $query['allowAlignment'] = '1';
}

function link_(array $query, array $extra = []): string
{
    return 'svg.php?' . http_build_query(array_merge($query, $extra));
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$cleared = $input['logoModules'] * $input['logoModules'];
$drawable = $input['logoModules'] - (2 * $input['logoMargin']);

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

    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th, td { text-align: left; padding: 6px 0; border-bottom: 1px solid var(--line); vertical-align: top; }
    th { font-weight: 500; color: var(--muted); width: 55%; }
    tr:last-child th, tr:last-child td { border-bottom: 0; }

    .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
    .buttons { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }

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
    .errors ul { margin: 0; padding-left: 18px; }
    .failure { color: var(--accent); font-size: 14px; margin: 0; }

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
    <h1>qr-gen</h1>
    <p class="lede">
        URL in, two SVGs out &mdash; one plain, one with artwork in the middle.
        Nothing is written to disk: every request encodes and renders from scratch,
        and a download regenerates rather than fetching a stored file.
    </p>

    <?php /*
        autocomplete="off" is the important attribute here, not a nicety.
        Chrome restores form field values on a soft reload, so a value that got
        into a field once survives every refresh — the URL and the defaults say
        one thing and the form shows another. That is what "the page did not
        reload properly" looks like from the outside, and calling the link
        afresh is the only thing that clears it. This stops it happening.
    */ ?>
    <form method="get" action="index.php" autocomplete="off">
        <div class="field-wide">
            <label for="url">URL</label>
            <input type="text" id="url" name="url" value="<?= e($input['url']) ?>" spellcheck="false">
        </div>

        <div>
            <label for="level">Error correction</label>
            <select id="level" name="level">
                <option value="<?= LEVEL_AUTO ?>"<?= $isAuto ? ' selected' : '' ?>>
                    auto &mdash; lowest that survives
                </option>
                <?php foreach (ErrorCorrection::all() as $option): ?>
                    <option value="<?= e($option) ?>"<?= $option === $input['level'] ? ' selected' : '' ?>>
                        <?= e($option . levelHint($option)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="logo">Logo</label>
            <select id="logo" name="logo">
                <?php if ($logos === []): ?>
                    <option value="">none in demo/logos</option>
                <?php endif; ?>
                <?php foreach ($logos as $file): ?>
                    <option value="<?= e($file) ?>"<?= $file === $input['logo'] ? ' selected' : '' ?>><?= e($file) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="logoModules">Logo box (modules, odd)</label>
            <input type="number" id="logoModules" name="logoModules" min="3" max="<?= MAX_LOGO_MODULES ?>" step="2" value="<?= $input['logoModules'] ?>">
        </div>

        <div>
            <label for="logoMargin">Logo margin (modules)</label>
            <input type="number" id="logoMargin" name="logoMargin" min="0" max="<?= MAX_LOGO_MARGIN ?>" value="<?= $input['logoMargin'] ?>">
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
            <label for="transparent">Transparent (plain only)</label>
        </div>

        <div class="check">
            <input type="checkbox" id="allowAlignment" name="allowAlignment" value="1"<?= $input['allowAlignment'] ? ' checked' : '' ?>>
            <label for="allowAlignment">Allow covering alignment patterns</label>
        </div>

        <div class="buttons">
            <button type="submit">Generate</button>
            <a class="btn-link btn-secondary" href="index.php">Reset</a>
        </div>
    </form>

    <script>
        // A focused number input treats the mouse wheel as a spinner, so
        // scrolling the page with the cursor over one silently changes it. With
        // step="2" on the logo box that turns 9 into 37 in fourteen notches,
        // and the value then looks like something someone typed on purpose.
        // Dropping focus lets the page scroll instead.
        document.querySelectorAll('input[type="number"]').forEach(function (field) {
            field.addEventListener('wheel', function () {
                if (document.activeElement === field) {
                    field.blur();
                }
            });
        });
    </script>

    <?php if ($input['errors'] !== []): ?>
        <div class="errors">
            <ul>
                <?php foreach ($input['errors'] as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($input['transparent']): ?>
        <div class="note">
            A logo needs an opaque backdrop: the cleared area around it has to read as light,
            otherwise whatever sits behind the symbol shows through and a scanner sees neither
            light nor dark. The transparent option therefore applies to the plain symbol only.
        </div>
    <?php endif; ?>

    <div class="cols">
        <div class="panel">
            <h2>Without logo</h2>
            <?php if ($plain !== null): ?>
                <div class="preview"><?= $plain ?></div>
                <div class="actions">
                    <a class="btn-link" href="<?= e(link_($query, ['download' => '1'])) ?>">Download SVG</a>
                    <a class="btn-link btn-secondary" href="<?= e(link_($query)) ?>" target="_blank" rel="noopener">Open raw</a>
                </div>
            <?php else: ?>
                <p class="failure"><?= e($plainFailure ?? 'Nothing rendered.') ?></p>
            <?php endif; ?>
        </div>

        <div class="panel">
            <h2>With logo</h2>
            <?php if ($withLogo !== null && $input['logo'] !== ''): ?>
                <div class="preview"><?= $withLogo ?></div>
                <div class="actions">
                    <a class="btn-link" href="<?= e(link_($query, ['variant' => 'logo', 'download' => '1'])) ?>">Download SVG</a>
                    <a class="btn-link btn-secondary" href="<?= e(link_($query, ['variant' => 'logo'])) ?>" target="_blank" rel="noopener">Open raw</a>
                </div>
            <?php elseif ($input['logo'] === ''): ?>
                <p class="failure">
                    No SVG in <code>demo/logos</code>. Drop one in and reload.
                </p>
            <?php else: ?>
                <p class="failure"><?= e($logoFailure ?? 'Nothing rendered.') ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($matrix !== null): ?>
        <div class="panel facts">
            <h2>What came out</h2>
            <div class="cols">
                <table>
                    <tr><th>Payload</th><td><?= strlen($input['url']) ?> bytes</td></tr>
                    <tr>
                        <th>Error correction</th>
                        <td>
                            <?= e($level->value() . levelHint($level->value())) ?>
                            <?php if ($isAuto): ?><br><small>chosen automatically: the lowest that survives this box</small><?php endif; ?>
                        </td>
                    </tr>
                    <tr><th>QR version</th><td><?= $matrix->version() ?> of 40</td></tr>
                    <tr><th>Modules</th><td><?= $matrix->size() ?> &times; <?= $matrix->size() ?> = <?= number_format($matrix->size() ** 2) ?></td></tr>
                    <?php if ($fitResult !== null): ?>
                        <tr>
                            <th>Allowance</th>
                            <td><?= sprintf('%.1f%% used of %.1f%%', $fitResult->clearedShare() * 100, $fitResult->budget() * 100) ?>,
                                <?= sprintf('%.0f%%', $fitResult->headroom() * 100) ?> headroom</td>
                        </tr>
                        <tr>
                            <th>Alignment pattern</th>
                            <td><?= $fitResult->compromisesAlignment()
                                ? $fitResult->placement()->coveredAlignmentModules() . ' modules given up'
                                : 'intact' ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
                <table>
                    <tr><th>Logo box</th><td><?= $input['logoModules'] ?> &times; <?= $input['logoModules'] ?> modules</td></tr>
                    <tr><th>Cleared</th><td><?= $cleared ?> modules, <?= sprintf('%.1f%%', $cleared / ($matrix->size() ** 2) * 100) ?> of the symbol</td></tr>
                    <tr><th>Margin</th><td><?= $input['logoMargin'] ?> module(s), leaving <?= $drawable ?> &times; <?= $drawable ?> to draw in</td></tr>
                    <tr><th>Logo width</th><td><?= sprintf('%.0f%%', $input['logoModules'] / $matrix->size() * 100) ?> of the symbol</td></tr>
                    <tr>
                        <th>Largest box that clears the finders</th>
                        <td><?= LogoBox::largestSideFor($matrix->size()) ?> modules
                            <?php if ($input['logoModules'] > LogoBox::largestSideFor($matrix->size())): ?>
                                &mdash; the box asked for is larger, which is why it is refused
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr><th>SVG size</th><td><?= number_format(strlen((string) $plain)) ?> vs <?= number_format(strlen((string) $withLogo)) ?> bytes</td></tr>
                </table>
            </div>
        </div>

        <details>
            <summary>SVG source, with logo</summary>
            <pre><?= e((string) $withLogo) ?></pre>
        </details>
    <?php endif; ?>

    <footer>
        The cleared area is weighed against the error correction level as a rule of thumb &mdash;
        modules and codewords are not the same unit. What is not a rule of thumb is the function
        patterns: finders, timing and alignment carry no error correction, and a box that touches
        one is refused. Whether the printed code scans is settled by a proof at final size on the
        real material, not by this page.
    </footer>
</main>
</body>
</html>
