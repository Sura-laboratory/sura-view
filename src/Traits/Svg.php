<?php
declare(strict_types=1);

namespace Sura\View\Traits;

/**
 * Trait Svg
 * @package Sura\View\Traits
 */
trait Svg
{
    /** @var string Корневая директория для SVG файлов (например: resources/svg/) */
    public string $svgPath = '';

    /** @var array Кэш загруженных SVG */
    private static array $svgCache = [];

    /**
     * Возвращает содержимое SVG файла по имени.
     * Если файл не найден, возвращает пустую строку или плейсхолдер.
     *
     * @param string $name Имя SVG (например: 'icons/user')
     * @param array $attrs Атрибуты SVG тега (например: ['class' => 'w-6 h-6', 'fill' => 'red'])
     *
     * @return string
     */
    public function svg(string $name, array $attrs = []): string
    {
        $path = rtrim($this->svgPath, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name) . '.svg';

        if (!file_exists($path)) {
            return '<!-- SVG not found: ' . htmlspecialchars($name) . ' -->';
        }

        // Кэшируем содержимое SVG
        if (!isset(self::$svgCache[$path])) {
            $content = file_get_contents($path);
            if ($content === false) {
                return '';
            }
            // Удаляем XML декларацию и DOCTYPE, оставляем только <svg>...
            $content = preg_replace('/^<\?xml.*?\?>|<!DOCTYPE[^>]*>/is', '', $content);
            self::$svgCache[$path] = trim($content);
        }

        $svg = self::$svgCache[$path];

        // Парсим атрибуты
        $attrString = '';
        foreach ($attrs as $key => $value) {
            $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
        }

        // Вставляем атрибуты внутрь тега <svg>
        $svg = preg_replace(
            '/^<svg\b(?<attrs>[^>]*)>/i',
            '<svg${attrs}' . $attrString . '>',
            $svg,
            1
        );

        return $svg;
    }

    //<editor-fold desc="Compile Directives">

    /**
     * Компилирует директиву @svg('icon-name')
     * Поддерживает второй параметр: массив атрибутов
     *
     * Пример: @svg('icons/user', ['class' => 'w-6 h-6'])
     *
     * @param string $expression Например: "('icons/user')" или "('icons/user', ['class' => '...'])"
     * @return string
     */
    protected function compile_svg($expression): string
    {
        return $this->phpTag . "echo \$this->svg{$expression}; ?>";
    }

    //</editor-fold>
}