<?php

namespace Sura\View;

use Sura\View\Traits\Language;
use Sura\View\Traits\Svg;

/**
 * Class myView
 */
class Templates extends View
{
    use Language;
    use Svg;

    public function __construct($svgPath=null, $missingLog=null)
    {
        if (!$svgPath) {
            // Укажите путь к SVG (относительный или абсолютный)
            $this->svgPath = __DIR__ . '/../assets/icons';
        }

        if ($missingLog) {
            // Если используется missingLog из Language
            $this->missingLog = __DIR__ . '/../logs/missing.log';
        }

    }    
}
