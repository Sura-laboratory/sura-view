<?php

namespace Sura\View\Traits;

/**
 * Trait Svg
 * Позволяет вставлять SVG-иконки в шаблоны через метод svg() и директиву @svg()
 */
trait Svg
{
    /** @var string Путь к папке с SVG-файлами */
    public string $svgPath = '';

    /** @var array Кэш загруженных SVG */
    private static array $svgCache = [];

    /**
     * Возвращает содержимое SVG-файла как строку с добавленными атрибутами.
     *
     * @param string $name Имя файла без расширения (например: 'user')
     * @param array $attrs Атрибуты: class, width, height и т.д.
     * @return string HTML <svg> или пустая строка
     */
    public function svg(string $name, array $attrs = []): string
    {
        if (!$name) {
            return '';
        }

        $key = $name . '|' . md5(serialize($attrs));
        if (isset(self::$svgCache[$key])) {
            return self::$svgCache[$key];
        }

        $file = rtrim($this->svgPath, '/') . '/' . $name . '.svg';
        if (!file_exists($file)) {
            $this->missingSvg($name);
            return '';
        }

        $content = file_get_contents($file);
        if ($content === false) {
            $this->missingSvg($name);
            return '';
        }

        // Удаляем XML и DOCTYPE
        $content = preg_replace('/<\?xml[^>]*\?'.'>/', '', $content);
        $content = preg_replace('/<!DOCTYPE[^>]*>/', '', $content);

        if (!preg_match('#<svg[^>]*>.*?</svg>#is', $content, $matches)) {
            $this->missingSvg($name);
            return '';
        }

        $svg = $matches[0];

        // Атрибуты по умолчанию
        $defaultAttrs = [
            'class' => 'svg-icon',
            'aria-hidden' => 'true',
            'role' => 'img',
        ];
        $attributes = array_merge($defaultAttrs, $attrs);

        // Формируем строку атрибутов
        $attrString = '';
        foreach ($attributes as $key => $value) {
            $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
        }

        // Добавляем атрибуты в тег <svg>
        $svg = preg_replace('/<svg\b/i', '<svg' . $attrString, $svg, 1);

        self::$svgCache[$key] = $svg;
        return $svg;
    }

    //<editor-fold desc="compile">

    /**
     * Компилирует директиву @svg('name', [...]) в PHP-код
     *
     * @param string $expression Например: "('user', ['class' => 'w-6'])"
     * @return string Скомпилированный PHP-выражение
     */
    protected function compile_svg($expression): string
    {
        return $this->phpTag . "echo \$this->svg{$expression}; ?>";
    }

    //</editor-fold>

    /**
     * Вызывается при отсутствии SVG-файла
     *
     * @param string $name Имя отсутствующего файла
     */
    private function missingSvg(string $name): void
    {
        if (!$this->missingLog) {
            return;
        }

        $message = "Missing SVG: {$name}.svg";

        $fz = @filesize($this->missingLog);
        $mode = $fz > 100000 ? 'w' : 'a'; // Перезапись при большом логе

        $fp = fopen($this->missingLog, $mode);
        fwrite($fp, $message . "\n");
        fclose($fp);
    }
}