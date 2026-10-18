<?php

declare(strict_types=1);

namespace PapyrusPDF\FontsZhHans;

use PapyrusPDF\Forms\{FormFontFallbacks, FormFontPackage};

/** Static Regular Simplified Chinese fonts; files are read lazily and subset-embedded. */
final class NotoZhHans implements FormFontPackage
{
    public const string SANS = 'NotoSansSC-Regular.ttf';
    public const string SERIF = 'NotoSerifSC-Regular.ttf';

    public static function path(string $file): string
    {
        if (!in_array($file, [self::SANS, self::SERIF], true)) {
            throw new \InvalidArgumentException('Unknown SC font file.');
        }

        return dirname(__DIR__).'/fonts/'.$file;
    }

    public static function formFallbacks(): FormFontFallbacks
    {
        return new FormFontFallbacks(
            chineseSans: self::path(self::SANS),
            chineseSerif: self::path(self::SERIF),
        );
    }
}
