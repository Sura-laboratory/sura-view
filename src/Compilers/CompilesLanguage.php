<?php

namespace Sura\View\Compilers;

use ArrayAccess;
use BadMethodCallException;
use Closure;
use Countable;
use Exception;
use InvalidArgumentException;

trait CompilesLanguage
{
    /**
     * Путь к директории с SVG (можно переопределить)
     * @var string
     */
    public static string $svgDirectory = '/assets/svg';

    /**
     * Компилирует @isset(...)
     */
    protected function compileIsset($expression): string
    {
        return $this->phpTag . "if(isset$expression): ?>";
    }

    /**
     * Завершает блок @isset
     */
    protected function compileEndIsset(): string
    {
        return $this->phpTag . 'endif; ?>';
    }

    /**
     * Завершает блок @empty
     */
    protected function compileEndEmpty(): string
    {
        return $this->phpTag . 'endif; ?>';
    }

    // ┌─────────────────────────────┐
    // │    @svgImg('name')          │
    // └─────────────────────────────┘
    /**
     * Компилирует @svgImg('logo')
     * Вставляет SVG как <img src="data:image/svg+xml;...">
     * Атрибуты не поддерживаются — только чистое изображение.
     *
     * @param string $expression Например: 'logo'
     * @return string Скомпилированный PHP-код
     */
    protected function compilesvgImg($expression): string
    {
        $pattern = '/\(\s*\'([^\']+)\'\s*,\s*\[.*?\]\s*\)/';

        if (!preg_match($pattern, $expression, $matches)) {

            //  "<!-- ".$expression." ".  $matches[1] ." 3Invalid @svgImg syntax -->";
        }

        if ($matches[2] === null) {
            $pattern = '/\(\s*\'([^\']+)\'\s*\)/';

            if (preg_match($pattern, $expression, $matches2)) {
                $matches[1] = $matches2[1];  // 'menu' (без кавычек и скобок)
                // return "Извлечено: $matches[1]\n";
            } else {
                // return "Совпадений не найдено.\n";
            }
        }

        $name = $matches[1];
        $svgPath = $this->getSvgPath($name);

        if (!file_exists($svgPath)) {
            $comment = htmlspecialchars("SVG not found: {$svgPath}");
            return "<!-- {$comment} -->";
        }

        $svgContent = file_get_contents($svgPath);
        if ($svgContent === false) {
            $comment = htmlspecialchars("Failed to load SVG: {$svgPath}");
            return "<!-- {$comment} -->";
        }

        // Очищаем SVG
        $svgContent = preg_replace('/<\?xml[^>]*\?>/', '', $svgContent);
        $svgContent = trim($svgContent);
        $svgContent = html_entity_decode($svgContent, ENT_QUOTES, 'UTF-8');

        // Кодируем в data URL
        $dataUrl = 'data:image/svg+xml;utf8,' . rawurlencode($svgContent);
        $imgHtml = '<img src="' . $dataUrl . '" alt="' . htmlspecialchars($name, ENT_QUOTES) . ' SVG">';

        // Экранируем для вставки в PHP
        $imgHtml = addcslashes($imgHtml, '\\$');

        return $this->phpTag . "echo '{$imgHtml}'; ?>";
    }

    // ┌──────────────────────────────────┐
    // │    @inlineSvg('name', [...])     │
    // └──────────────────────────────────┘
    /**
     * Компилирует @inlineSvg('logo', ['class' => 'icon'])
     * Вставляет сам тег <svg> с возможностью изменения атрибутов.
     *
     * @param string $expression Например: 'logo' или 'logo', ['class' => '...']
     * @return string Скомпилированный PHP-код
     */
    protected function compileinlineSvg($expression): string
    {
        $pattern = '/^\s*\(\s*\'([^\']+)\'\s*,\s*(\[.*?\])\s*\)\s*$/';
        if (!preg_match($pattern, $expression, $matches)) {
            return "<!-- ". var_dump($matches) ." Invalid @inlineSvg syntax -->";
        }

        $name = $matches[1];
        $attributesCode = $matches[2] ?? '[]';

        $svgPath = $this->getSvgPath($name);

        if (!file_exists($svgPath)) {
            $comment = htmlspecialchars("SVG not found: {$svgPath}");
            return "<!-- {$comment} -->";
        }

        $svgContent = file_get_contents($svgPath);
        if ($svgContent === false) {
            $comment = htmlspecialchars("Failed to load SVG: {$svgPath}");
            return "<!-- {$comment} -->";
        }

        $svgContent = preg_replace('/<\?xml[^>]*\?>/', '', $svgContent);
        $svgContent = trim($svgContent);

        // Если нет атрибутов — вставляем напрямую
        if ($attributesCode === '[]') {
            $svgContent = addcslashes($svgContent, '\\$');
            return $this->phpTag . "echo '{$svgContent}'; ?>";
        }

        // Если есть атрибуты — обрабатываем при выполнении
        return $this->phpTag . "echo \$this->compiledInlineSvgWithAttributes('{$name}', {$attributesCode}); ?>";
    }

    /**
     * Возвращает путь к SVG-файлу
     */
    protected function getSvgPath(string $name): string
    {
        return rtrim($_SERVER['DOCUMENT_ROOT'], '/') 
            . '/' . ltrim(static::$svgDirectory, '/') 
            . '/' . $name . '.svg';
    }

    // ┌──────────────────────────────────┐
    // │    Методы для выполнения         │
    // └──────────────────────────────────┘

    /**
     * Вызывается при рендере, если @inlineSvg использует атрибуты
     */
    public function compiledInlineSvgWithAttributes(string $name, array $attributes = []): string
    {
        $path = $this->getSvgPath($name);

        if (!file_exists($path)) {
            return '<!-- SVG not found -->';
        }

        $svgContent = file_get_contents($path);
        if ($svgContent === false) {
            return '<!-- Failed to load SVG -->';
        }

        $svgContent = preg_replace('/<\?xml[^>]*\?>/', '', $svgContent);
        $svgContent = trim($svgContent);

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadXML($svgContent, LIBXML_NOERROR | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $svgNode = $dom->documentElement;

        foreach ($attributes as $key => $value) {
            $svgNode->setAttribute($key, $value);
        }

        $svgContent = $dom->saveHTML($svgNode);
        libxml_clear_errors();

        return $svgContent;
    }

    /**
     * Устаревшая директива @svg — можно удалить или оставить как алиас
     * @deprecated Используйте @svgImg или @inlineSvg
     */
    protected function compilesvg($expression): string
    {
        // Можно оставить как алиас @svgImg
        return $this->compilesvgImg($expression);
    }
}