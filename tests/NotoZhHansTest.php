<?php

declare(strict_types=1);

namespace Vicam\Pdf\FontsZhHans\Tests;

use Vicam\Pdf\FontsZhHans\NotoZhHans;
use Vicam\Pdf\Forms\{FieldAssignment, FieldValue, FormFillRequest};
use Vicam\Pdf\PdfEngineFactory;
use PHPUnit\Framework\TestCase;

final class NotoZhHansTest extends TestCase
{
    private const array HASHES = [
        'NotoSerifSC-Regular.ttf' => '04e8390ae48501976db2bc8228e9819dd0798a2dacf29f8e8d0b58a17d514d85',
        'NotoSansSC-Regular.ttf' => '9cdb15629d85f80bc058033b0b3b5444d63f69e976e54a9ae4c2d6f37f96c565',
    ];

    public function testManifestAndStaticRegularFontsMatchProvenance(): void
    {
        $files = array_filter(get_object_vars(NotoZhHans::formFallbacks()));
        self::assertCount(2, $files);
        $directory = sys_get_temp_dir().'/papyrus-cjk-'.bin2hex(random_bytes(6));
        self::assertTrue(mkdir($directory, 0700));
        try {
            $engine = (new PdfEngineFactory())->create($directory);
            foreach ($files as $file) {
                self::assertIsString($file);
                self::assertFileExists($file);
                self::assertSame([\Vicam\Pdf\Forms\FormFontPackage::class], array_values(class_implements(NotoZhHans::class)));
                if (isset(self::HASHES[basename($file)])) {
                    self::assertSame(self::HASHES[basename($file)], hash_file('sha256', $file));
                }
                $bytes = file_get_contents($file);
                self::assertIsString($bytes);
                $font = $engine->readFont($bytes);
                self::assertTrue($font->embedding->subsettingAllowed);
                self::assertStringEndsWith('-Regular', $font->postScriptName);
                foreach (['A', '東', '京'] as $character) {
                    self::assertNotNull($font->glyphFor(mb_ord($character)));
                }
            }
        } finally {
            rmdir($directory);
        }
    }

    public function testInstalledPackageIsDiscoveredAndFormAppearanceIsSmall(): void
    {
        $directory = sys_get_temp_dir().'/papyrus-cjk-form-'.bin2hex(random_bytes(6));
        self::assertTrue(mkdir($directory, 0700));
        try {
            $path = $directory.'/source.pdf';
            self::assertNotFalse(file_put_contents($path, $this->source()));
            $request = new FormFillRequest([new FieldAssignment('name', FieldValue::text('中华人民共和国'))]);
            $engine = (new PdfEngineFactory())->create($directory);
            $result = $engine->fillForm($path, $request);
            self::assertContains('form.font-substituted', $result->report->warnings);
            self::assertSame('NotoSerifSC-Regular', $result->report->fontSubstitutions[0]->substitutedFace);
            $result->artifact->publishTo($directory.'/filled.pdf');
            self::assertLessThan(100_000, filesize($directory.'/filled.pdf'));
            self::assertSame('name', $engine->inspectForm($directory.'/filled.pdf')->fields[0]->name->fullyQualified);
            $disabled = (new PdfEngineFactory(discoverFormFonts: false))->create($directory);
            try {
                $disabled->fillForm($path, $request)->artifact->close();
                self::fail('Without discovery a Japanese program is unavailable.');
            } catch (\Vicam\Pdf\Core\PdfException $error) {
                // PapyrusPDF 0.3.6 reports the uncovered value; earlier releases report the unusable field font.
                self::assertContains($error->diagnostic, ['form.glyph-unavailable', 'form.font-unsupported']);
            }
        } finally {
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    public function testUnknownFilesAreRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        NotoZhHans::path('../font.ttf');
    }

    /** Original blank synthetic form, no corpus or third-party PDF bytes. */
    private function source(): string
    {
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R /AcroForm 3 0 R >>',
            '<< /Type /Pages /Kids [4 0 R] /Count 1 >>',
            '<< /Fields [6 0 R] /DA (/Mincho 12 Tf 0 g) /DR << /Font << /Mincho 5 0 R >> >> >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 300] /Resources << >> /Annots [6 0 R] >>',
            '<< /Type /Font /Subtype /Type0 /BaseFont /SimSun-Song /Encoding /UniGB-UCS2-H /DescendantFonts [] >>',
            '<< /Type /Annot /Subtype /Widget /FT /Tx /T (name) /Rect [20 230 280 260] /F 4 /P 4 0 R >>',
        ];
        $bytes = "%PDF-1.7\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($bytes);
            $bytes .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($bytes);
        $bytes .= "xref\n0 7\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $bytes .= sprintf("%010d 00000 n \n", $offset);
        }

        return $bytes."trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}
