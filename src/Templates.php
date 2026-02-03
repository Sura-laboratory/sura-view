<?php

namespace Sura\View;

use Sura\View\Traits\Language;
use Sura\View\Traits\SvgHelper; // Подключаем новый трейт

/**
 * Class Templates
 */
class Templates extends View
{
    use Language;
    use SvgHelper; // Используем трейт для SVG

    public function __construct()
    {
        parent::__construct($templatePath = null, $compiledPath = null, $mode = 0, $commentMode = 0);

        $this->SvgHelper();

        // Явно вызываем инициализацию трейта, если нужно (опционально, но безопасно)
        // Метод `SvgHelper()` будет вызван автоматически благодаря View,
        // но можно добавить дополнительную логику при необходимости

        // Например, переопределить путь к SVG по умолчанию
        // $this->svgDirectory = '/assets/icons';

        // Можно оставить пустым, если всё работает — просто для ясности
    }    
}