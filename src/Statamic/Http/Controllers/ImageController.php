<?php

declare(strict_types=1);

namespace Redcodede\QrGen\Statamic\Http\Controllers;

use Illuminate\Http\Request;
use Redcodede\QrGen\Qr\Exception\QrGenException;
use Redcodede\QrGen\Qr\Settings\EffectiveSettings;
use Redcodede\QrGen\Qr\Settings\Variant;
use Redcodede\QrGen\Statamic\Artwork;
use Redcodede\QrGen\Statamic\Settings\SettingsStore;
use Redcodede\QrGen\Statamic\Symbols;

/**
 * Liefert ein einzelnes Symbol aus, zum Herunterladen oder zum Ansehen.
 *
 * Erzeugt wird bei jeder Anfrage neu und direkt in die Antwort geschrieben.
 * Deshalb `no-store`: es gibt keine gespeicherte Kopie, und eine
 * zwischengespeicherte waere die einzige.
 *
 * Die Adresse ist signiert. Ohne Signatur waere das ein kostenloser
 * QR-Generator auf fremder Domain, mit dem sich Codes fuer beliebige Links
 * erzeugen liessen.
 */
class ImageController
{
    /**
     * Der Name der Bild-Route.
     *
     * Vergeben wird er in `routes/actions.php` als `qr-gen.image`. Statamic
     * haengt allen Action-Routen `statamic.` davor, weil es die ganze Gruppe
     * so benennt. Wer die Route auflöst, muss also den langen Namen nehmen,
     * und damit dieser Umstand nur einmal im Paket steht, steht er hier.
     */
    public const ROUTE = 'statamic.qr-gen.image';

    public function show(Request $request)
    {
        abort_unless($request->hasValidSignature(), 403);

        $url = (string) $request->query('url', '');
        $variant = (string) $request->query('variant', Variant::PLAIN);
        $format = $request->query('format') === 'png' ? 'png' : 'svg';

        abort_if($url === '' || !Variant::isKnown($variant), 404);

        $global = SettingsStore::global();
        $effective = EffectiveSettings::from($global, $url);

        // Was global abgeschaltet ist, gibt es auch dann nicht, wenn jemand
        // eine alte signierte Adresse aufhebt.
        abort_if($format === 'svg' && !$effective->offersSvg(), 404);
        abort_if($format === 'png' && !$effective->offersPng(), 404);

        // Dasselbe Argument wie eine Zeile hoeher, fuer den Typ statt fuer das
        // Format: ein abgeschalteter Typ ist auch ueber eine alte signierte
        // Adresse nicht zu bekommen.
        abort_unless($effective->shows($variant), 404);

        $logo = Artwork::load($request->query('logo'));
        $mitMarke = Variant::isLabel($variant)
            ? $logo !== null
            : ($variant === Variant::LOGO && $logo !== null);

        abort_if($variant === Variant::LOGO && !$mitMarke, 404);

        try {
            [$bytes, $mime, $extension] = Symbols::image($url, $variant, $logo, $effective, $format);
        } catch (QrGenException $exception) {
            // Der Kern wirft mit Begruendung. Die gehoert ins Log, nicht als
            // 500er auf den Bildschirm.
            //
            // Die Adresse geht nur als Host und Laenge hinein, nicht ganz. Der
            // Kern nennt aus demselben Grund die Laenge der Nutzlast statt der
            // Nutzlast: ein Paket, das zusagt, verarbeitete Adressen nicht zu
            // speichern, kann sie nicht in ein Log schreiben. Zum Nachstellen
            // reicht beides zusammen mit Variante und Format.
            logger()->warning('qr-gen: Symbol nicht erzeugt', [
                'host' => (string) parse_url($url, PHP_URL_HOST),
                'zeichen' => strlen($url),
                'variante' => $variant,
                'format' => $format,
                'grund' => $exception->getMessage(),
            ]);

            abort(422, $exception->getMessage());
        }

        $disposition = $request->boolean('download')
            ? 'attachment; filename="' . self::filename($url, $variant, $mitMarke, $extension) . '"'
            : 'inline';

        return response($bytes, 200, [
            'Content-Type' => $mime . ($format === 'svg' ? '; charset=utf-8' : ''),
            'Content-Disposition' => $disposition,
            'Content-Length' => (string) strlen($bytes),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Auf einen ASCII-Slug reduziert statt escaped, weil der Wert in einen
     * Content-Disposition-Header geht, wo ein Zeilenumbruch oder ein
     * Anfuehrungszeichen eigene Header schreiben liesse.
     *
     * Der Pfad geht mit in den Namen, nicht nur der Host. Sonst heissen alle
     * Dateien einer Domain gleich, und wer die Codes mehrerer Hersteller
     * herunterlaedt, hat einen Ordner voll durchnummerierter Kopien, bei denen
     * niemand mehr sieht, welche zu wem gehoert.
     */
    private static function filename(string $url, string $variant, bool $mitMarke, string $extension): string
    {
        $teile = (array) parse_url($url);

        $kennung = ($teile['host'] ?? '') . ' ' . ($teile['path'] ?? '');
        $slug = trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', strtolower($kennung)), '-');

        // Vier Typen, vier Dateinamen. Wer sie alle herunterlaedt, hat sie
        // sonst viermal gleich benannt im Ordner liegen.
        if ($variant === Variant::LABEL) {
            $zusatz = '-etikett';
        } elseif ($variant === Variant::LABEL_COLOR) {
            $zusatz = '-etikett-farbig';
        } else {
            $zusatz = $mitMarke ? '-logo' : '';
        }

        return 'qr-' . substr($slug === '' ? 'code' : $slug, 0, 60) . $zusatz . '.' . $extension;
    }
}
