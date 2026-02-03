<?php

namespace Sura\View\Traits;

trait SvgHelper
{
    /**
     * Настройка пути по умолчанию для SVG-файлов.
     * Можно переопределить в экземпляре View.
     *
     * @var string
     */
    public static string $svgDirectory = '/assets/svg';

    /**
     * Этот метод будет автоматически вызван, потому что имя трейта = SvgHelper,
     * и View ищет метод с таким именем.
     */
    public function SvgHelper(): void
    {
        $this->registerSvgDirective();
    }

    /**
     * Регистрация директивы @svg
     */
    protected function registerSvgDirective(): void
    {
        $this->customDirectives['svg'] = function ($expression) {
            return "<?php echo \$this->inlineSvg{$expression}; ?>";
        };
    }

    /**
     * Загружает и модифицирует SVG
     */
    protected function svg(string $path, array $attributes = []): string
    {
        if (!file_exists($path)) {
            return '<!-- SVG not found: ' . htmlspecialchars($path) . ' -->';
        }

        $svgContent = file_get_contents($path);

        if ($svgContent === false) {
            return '<!-- Failed to load SVG: ' . htmlspecialchars($path) . ' -->';
        }

        // Удаляем XML декларацию
        $svgContent = preg_replace('/<\?xml[^>]*\?>/', '', $svgContent);

        if (!empty($attributes)) {
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadXML($svgContent, LIBXML_NOERROR | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $svgNode = $dom->documentElement;

            foreach ($attributes as $key => $value) {
                $svgNode->setAttribute($key, $value);
            }

            $svgContent = $dom->saveHTML($svgNode);
            libxml_clear_errors();
        }

        return trim($svgContent);
    }

    /**
     * Вставка SVG по имени
     *
     * @param string $name Название SVG (без расширения)
     * @param array $attributes Атрибуты SVG
     * @return string
     */
    public function inlineSvg(string $name, array $attributes = []): string
    {
        $directory = static::$svgDirectory;
        $path = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . ltrim($directory, '/') . '/' . $name . '.svg';
        var_dump($path);
        return $this->svg($path, $attributes);
    }
}