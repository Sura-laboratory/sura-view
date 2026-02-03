<?php
declare(strict_types=1);

namespace Sura\View\Traits;

/**
 * Трейт Lang
 * Предоставляет функциональность для локализации строк в приложении.
 * 
 * @package Sura\View
 */
trait Language
{
    /** @var string Путь к файлу журнала отсутствующих переводов. Если пусто — отсутствующие ключи не сохраняются. */
    public string $missingLog = '';

    /** @var array Массив со словарём переводов */
    public static array $dictionary = [];

    /**
     * Пытается перевести фразу, если она существует в массиве static::$dictionary.
     * Если перевод не найден, возвращается исходная фраза без изменений.
     *
     * @param string $phrase Фраза для перевода
     * @return string Переведённая строка или оригинальная, если перевод отсутствует
     */
    public function _e($phrase): string
    {
        if (!\array_key_exists($phrase, static::$dictionary)) {
            $this->missingTranslation($phrase);
            return $phrase;
        }

        return static::$dictionary[$phrase];
    }

    /**
     * Аналогично _e(), но дополнительно обрабатывает строку с помощью sprintf().
     * Позволяет вставлять переменные в переведённый текст.
     * Если перевод не найден, возвращается оригинальная строка с подставленными значениями.
     *
     * @param string $phrase Фраза для перевода (может содержать плейсхолдеры, например %s)
     * @return string Обработанная строка с подстановкой значений
     */
    public function _ef($phrase): string
    {
        $argv = \func_get_args();
        $r = $this->_e($phrase);
        $argv[0] = $r; // заменяем первую переменную на переведённую строку
        $result = sprintf(...$argv);
        $result = ($result === false) ? $r : $result;
        return $result;
    }

    /**
     * Возвращает форму слова в зависимости от числа (единственное или множественное число).
     * Примечание: структура перевода должна быть такой: 
     * $msg['Person'] = 'Человек'; 
     * $msg['Person']['p'] = 'Люди';
     *
     * @param string $phrase Форма единственного числа
     * @param string $phrases Форма множественного числа
     * @param int $num Число для определения формы
     * @return string Переведённая строка в нужной форме
     */
    public function _n($phrase, $phrases, $num = 0): string
    {
        if (!\array_key_exists($phrase, static::$dictionary)) {
            $this->missingTranslation($phrase);
            return ($num <= 1) ? $phrase : $phrases;
        }

        return ($num <= 1) ? $this->_e($phrase) : $this->_e($phrases);
    }

    //<editor-fold desc="compile">

    /**
     * Компилирует директиву @_e в PHP-код.
     *
     * @param string $expression Выражение, переданное в @_e
     * @return string Скомпилированный PHP-код
     */
    protected function compile_e($expression): string
    {
        return $this->phpTag . "echo \$this->_e{$expression}; ?>";
    }

    /**
     * Компилирует директиву @_ef в PHP-код.
     *
     * @param string $expression Выражение, переданное в @_ef
     * @return string Скомпилированный PHP-код
     */
    protected function compile_ef($expression): string
    {
        return $this->phpTag . "echo \$this->_ef{$expression}; ?>";
    }

    /**
     * Компилирует директиву @_n в PHP-код.
     *
     * @param string $expression Выражение, переданное в @_n
     * @return string Скомпилированный PHP-код
     */
    protected function compile_n($expression): string
    {
        return $this->phpTag . "echo \$this->_n{$expression}; ?>";
    }

    //</editor-fold>

    /**
     * Записывает информацию об отсутствующем переводе в лог-файл.
     * Если путь к файлу не задан ($this->missingLog), запись не производится.
     *
     * @param string $txt Текст, который не был найден в словаре
     * @return bool Всегда возвращает true после выполнения
     */
    private function missingTranslation(string $txt): bool
    {
        if (!$this->missingLog) {
            return true; // если файл не задан — пропускаем запись
        }

        $fz = @\filesize($this->missingLog);
        $mode = 'a';

        if (\is_object($txt) || \is_array($txt)) {
            $txt = \print_r($txt, true);
        }

        // Перезаписываем файл, если он больше 100 КБ
        if ($fz > 100000) {
            $mode = 'w';
        }

        $fp = \fopen($this->missingLog, 'w');
        \fwrite($fp, $txt . "\n");
        \fclose($fp);
        return true;
    }
}