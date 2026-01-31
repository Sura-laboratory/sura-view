<?php

namespace Sura\View\Compilers;

use ArrayAccess;
use BadMethodCallException;
use Closure;
use Countable;
use Exception;
use InvalidArgumentException;

trait CompilesCompile
{
    /**
     * Authentication. Sets with a user,role and permission
     *
     * @param string $user
     * @param null   $role
     * @param array  $permission
     */
    public function setAuth($user = '', $role = null, $permission = []): void
    {
        $this->currentUser = $user;
        $this->currentRole = $role;
        $this->currentPermission = $permission;
    }

    /**
     * run the blade engine. It returns the result of the code.
     *
     * @param string $string HTML to parse
     * @param array  $data   It is an associative array with the datas to display.
     * @return string It returns a parsed string
     * @throws Exception
     */
    public function runString($string, $data = []): string
    {
        $php = $this->compileString($string);
        $obLevel = \ob_get_level();
        \ob_start();
        \extract($data, EXTR_SKIP);
        $previousError = \error_get_last();
        try {
            @eval('?' . '>' . $php);
        } catch (Exception $e) {
            while (\ob_get_level() > $obLevel) {
                \ob_end_clean();
            }
            throw $e;
        } catch (\Throwable $e) { // PHP >= 7
            while (\ob_get_level() > $obLevel) {
                \ob_end_clean();
            }
            $this->showError('runString', $e->getMessage() . ' ' . $e->getCode(), true);
            return '';
        }
        $lastError = \error_get_last(); // PHP 5.6
        if ($previousError != $lastError && $lastError['type'] == E_PARSE) {
            while (\ob_get_level() > $obLevel) {
                \ob_end_clean();
            }
            $this->showError('runString', $lastError['message'] . ' ' . $lastError['type'], true);
            return '';
        }
        return $this->postRun(\ob_get_clean());
    }

    /**
     * Compile the given Blade template contents.
     *
     * @param string $value
     * @return string
     */
    public function compileString($value): string
    {
        $result = '';
        if (\strpos($value, '@verbatim') !== false) {
            $value = $this->storeVerbatimBlocks($value);
        }
        $this->footer = [];
        // Here we will loop through all the tokens returned by the Zend lexer and
        // parse each one into the corresponding valid PHP. We will then have this
        // template as the correctly rendered PHP that can be rendered natively.
        foreach (\token_get_all($value) as $token) {
            $result .= \is_array($token) ? $this->parseToken($token) : $token;
        }
        if (!empty($this->verbatimBlocks)) {
            $result = $this->restoreVerbatimBlocks($result);
        }
        // If there are any footer lines that need to get added to a template we will
        // add them here at the end of the template. This gets used mainly for the
        // template inheritance via the extends keyword that should be appended.
        if (\count($this->footer) > 0) {
            $result = \ltrim($result, PHP_EOL)
                . PHP_EOL . \implode(PHP_EOL, \array_reverse($this->footer));
        }
        return $result;
    }

    /**
     * Store the verbatim blocks and replace them with a temporary placeholder.
     *
     * @param string $value
     * @return string
     */
    protected function storeVerbatimBlocks($value): string
    {
        return \preg_replace_callback('/(?<!@)@verbatim(.*?)@endverbatim/s', function($matches) {
            $this->verbatimBlocks[] = $matches[1];
            return $this->verbatimPlaceholder;
        }, $value);
    }

    /**
     * Parse the tokens from the template.
     *
     * @param array $token
     *
     * @return string
     *
     * @see View::compileStatements
     * @see View::compileExtends
     * @see View::compileComments
     * @see View::compileEchos
     */
    protected function parseToken($token): string
    {
        [$id, $content] = $token;
        if ($id == T_INLINE_HTML) {
            foreach ($this->compilers as $type) {
                $content = $this->{"compile$type"}($content);
            }
        }
        return $content;
    }

    /**
     * Replace the raw placeholders with the original code stored in the raw blocks.
     *
     * @param string $result
     * @return string
     */
    protected function restoreVerbatimBlocks($result): string
    {
        $result = \preg_replace_callback('/' . \preg_quote($this->verbatimPlaceholder) . '/', function() {
            return \array_shift($this->verbatimBlocks);
        }, $result);
        $this->verbatimBlocks = [];
        return $result;
    }

    /**
     * it calculates the relative path of a web.<br>
     * This function uses the current url and the baseurl
     *
     * @param string $relativeWeb . Example img/images.jpg
     * @return string  Example ../../img/images.jpg
     */
    public function relative($relativeWeb): string
    {
        return $this->assetDict[$relativeWeb] ?? ($this->relativePath . $relativeWeb);
    }

    /**
     * It adds an alias to the link of the resources.<br>
     * addAssetDict('name','url/res.jpg')<br>
     * addAssetDict(['name'=>'url/res.jpg','name2'=>'url/res2.jpg']);
     *
     * @param string|array $name example 'css/style.css', you could also add an array
     * @param string       $url  example https://www.web.com/style.css'
     */
    public function addAssetDict($name, $url = ''): void
    {
        if (\is_array($name)) {
            $this->assetDict = \array_merge($this->assetDict, $name);
        } else {
            $this->assetDict[$name] = $url;
        }
    }

    public function addAssetDictCDN($name, $url = ''): void
    {
        if (\is_array($name)) {
            $this->assetDictCDN = \array_merge($this->assetDictCDN, $name);
        } else {
            $this->assetDictCDN[$name] = $url;
        }
    }

    /**
     * Compile the push statements into valid PHP.
     *
     * @param string $expression
     * @return string
     * @see View::startPush
     */
    public function compilePush($expression): string
    {
        return $this->phpTag . "\$this->startPush$expression; ?>";
    }

    /**
     * Compile the push statements into valid PHP.
     *
     * @param string $expression
     * @return string
     * @see View::startPush
     */
    public function compilePushOnce($expression): string
    {
        $key = '$__pushonce__' . \trim(\substr($expression, 2, -2));
        return $this->phpTag . "if(!isset($key)): $key=1;  \$this->startPush$expression; ?>";
    }

    /**
     * Compile the push statements into valid PHP.
     *
     * @param string $expression
     * @return string
     * @see View::startPush
     */
    public function compilePrepend($expression): string
    {
        return $this->phpTag . "\$this->startPush$expression; ?>";
    }

    /**
     * Start injecting content into a push section.
     *
     * @param string $section
     * @param string $content
     * @return void
     */
    public function startPush($section, $content = ''): void
    {
        if ($content === '') {
            if (\ob_start()) {
                $this->pushStack[] = $section;
            }
        } else {
            $this->extendPush($section, $content);
        }
    }

    /*
     * endswitch tag
     */
    /**
     * Append content to a given push section.
     *
     * @param string $section
     * @param string $content
     * @return void
     */
    protected function extendPush($section, $content): void
    {
        if (!isset($this->pushes[$section])) {
            $this->pushes[$section] = []; // start an empty section
        }
        if (!isset($this->pushes[$section][$this->renderCount])) {
            $this->pushes[$section][$this->renderCount] = $content;
        } else {
            $this->pushes[$section][$this->renderCount] .= $content;
        }
    }

    /**
     * Start injecting content into a push section.
     *
     * @param string $section
     * @param string $content
     * @return void
     */
    public function startPrepend($section, $content = ''): void
    {
        if ($content === '') {
            if (\ob_start()) {
                \array_unshift($this->pushStack[], $section);
            }
        } else {
            $this->extendPush($section, $content);
        }
    }

    /**
     * Stop injecting content into a push section.
     *
     * @return string
     */
    public function stopPush(): string
    {
        if (empty($this->pushStack)) {
            $this->showError('stopPush', 'Cannot end a section without first starting one', true);
        }
        $last = \array_pop($this->pushStack);
        $this->extendPush($last, \ob_get_clean());
        return $last;
    }

    /**
     * Stop injecting content into a push section.
     *
     * @return string
     */
    public function stopPrepend(): string
    {
        if (empty($this->pushStack)) {
            $this->showError('stopPrepend', 'Cannot end a section without first starting one', true);
        }
        $last = \array_shift($this->pushStack);
        $this->extendStartPush($last, \ob_get_clean());
        return $last;
    }

    /**
     * Append content to a given push section.
     *
     * @param string $section
     * @param string $content
     * @return void
     */
    protected function extendStartPush($section, $content): void
    {
        if (!isset($this->pushes[$section])) {
            $this->pushes[$section] = []; // start an empty section
        }
        if (!isset($this->pushes[$section][$this->renderCount])) {
            $this->pushes[$section][$this->renderCount] = $content;
        } else {
            $this->pushes[$section][$this->renderCount] = $content . $this->pushes[$section][$this->renderCount];
        }
    }

    /**
     * Get the string contents of a push section.
     *
     * @param string $section the name of the section
     * @param string $default the default name of the section is not found.
     * @return string
     */
    public function yieldPushContent($section, $default = ''): string
    {
        if ($section === null || $section === '') {
            return $default;
        }
        if ($section[-1] === '*') {
            $keys = array_keys($this->pushes);
            $findme = rtrim($section, '*');
            $result = "";
            foreach ($keys as $key) {
                if (strpos($key, $findme) === 0) {
                    $result .= \implode(\array_reverse($this->pushes[$key]));
                }
            }
            return $result;
        }
        if (!isset($this->pushes[$section])) {
            return $default;
        }
        return \implode(\array_reverse($this->pushes[$section]));
    }

    /**
     * Get the string contents of a push section.
     *
     * @param int|string $each if "int", then it split the foreach every $each numbers.<br>
     *                         if "string" or "c3", then it means that it will split in 3 columns<br>
     * @param string     $splitText
     * @param string     $splitEnd
     * @return string
     */
    public function splitForeach($each = 1, $splitText = ',', $splitEnd = ''): string
    {
        $loopStack = static::last($this->loopsStack); // array(7) { ["index"]=> int(0) ["remaining"]=> int(6) ["count"]=> int(5) ["first"]=> bool(true) ["last"]=> bool(false) ["depth"]=> int(1) ["parent"]=> NULL }
        if (($loopStack['index']) == $loopStack['count'] - 1) {
            return $splitEnd;
        }
        $eachN = 0;
        if (is_numeric($each)) {
            $eachN = $each;
        } elseif (strlen($each) > 1) {
            if ($each[0] === 'c') {
                $eachN = round($loopStack['count'] / substr($each, 1));
            }
        } else {
            $eachN = PHP_INT_MAX;
        }
        if (($loopStack['index'] + 1) % $eachN === 0) {
            return $splitText;
        }
        return '';
    }

    /**
     * Return the last element in an array passing a given truth test.
     *
     * @param array         $array
     * @param callable|null $callback
     * @param mixed         $default
     * @return mixed
     */
    public static function last($array, ?callable $callback = null, $default = null)
    {
        if (\is_null($callback)) {
            return empty($array) ? static::value($default) : \end($array);
        }
        return static::first(\array_reverse($array), $callback, $default);
    }

    /**
     * Return the default value of the given value.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function value($value)
    {
        return $value instanceof Closure ? $value() : $value;
    }

    /**
     * Return the first element in an array passing a given truth test.
     *
     * @param array         $array
     * @param callable|null $callback
     * @param mixed         $default
     * @return mixed
     */
    public static function first($array, ?callable $callback = null, $default = null)
    {
        if (\is_null($callback)) {
            return empty($array) ? static::value($default) : \reset($array);
        }
        foreach ($array as $key => $value) {
            if ($callback($key, $value)) {
                return $value;
            }
        }
        return static::value($default);
    }

    /**
     * @param string $name
     * @param        $args []
     * @return string
     * @throws BadMethodCallException
     */
    public function __call($name, $args)
    {
        if ($name === 'if') {
            return $this->registerIfStatement($args[0] ?? null, $args[1] ?? null);
        }
        $this->showError('call', "function $name is not defined<br>", true, true);
        return '';
    }

    /**
     * Register an "if" statement directive.
     *
     * @param string   $name
     * @param callable $callback
     * @return string
     */
    public function registerIfStatement($name, callable $callback): string
    {
        $this->conditions[$name] = $callback;
        $this->directive($name, function($expression) use ($name) {
            $tmp = $this->stripParentheses($expression);
            return $expression !== ''
                ? $this->phpTag . " if (\$this->check('$name', $tmp)): ?>"
                : $this->phpTag . " if (\$this->check('$name')): ?>";
        });
        $this->directive('else' . $name, function($expression) use ($name) {
            $tmp = $this->stripParentheses($expression);
            return $expression !== ''
                ? $this->phpTag . " elseif (\$this->check('$name', $tmp)): ?>"
                : $this->phpTag . " elseif (\$this->check('$name')): ?>";
        });
        $this->directive('end' . $name, function() {
            return $this->phpTag . ' endif; ?>';
        });
        return '';
    }

    /**
     * Check the result of a condition.
     *
     * @param string $name
     * @param array  $parameters
     * @return bool
     */
    public function check($name, ...$parameters): bool
    {
        return \call_user_func($this->conditions[$name], ...$parameters);
    }

    /**
     * @param bool   $bool
     * @param string $view  name of the view
     * @param array  $value arrays of values
     * @return string
     * @throws Exception
     */
    public function includeWhen($bool = false, $view = '', $value = []): string
    {
        if ($bool) {
            return $this->runChild($view, $value);
        }
        return '';
    }

    /**
     * Macro of function run. Runchild backups the operations, so it is ideal to run as a child process without
     * intervining with other processes.
     *
     * @param       $view
     * @param array $variables
     * @return string
     * @throws Exception
     */
    public function runChild($view, $variables = []): string
    {
        if (\is_array($variables)) {
            if ($this->includeScope) {
                $backup = $this->variables;
            } else {
                $backup = null;
            }
            $newVariables = \array_merge($this->variables, $variables);
            $backupControlStack = $this->controlStack;
            $backupSectionStack = $this->sectionStack;
            $backupLookStack = $this->loopsStack;
        } else {
            $this->showError('run/include', "RunChild: Include/run variables should be defined as array ['idx'=>'value']", true);
            return '';
        }
        $r = $this->runInternal($view, $newVariables, false, $this->isRunFast);
        if ($backup !== null) {
            $this->variables = $backup;
        }
        $this->controlStack = $backupControlStack;
        $this->sectionStack = $backupSectionStack;
        $this->loopsStack = $backupLookStack;
        return $r;
    }

    /**
     * run the blade engine. It returns the result of the code.
     *
     * @param string $view
     * @param array  $variables
     * @param bool   $forced  if true then it recompiles no matter if the compiled file exists or not.
     * @param bool   $runFast if true then the code is not compiled neither checked, and it runs directly the compiled
     *                        version.
     * @return string
     * @throws Exception
     * @noinspection PhpUnusedParameterInspection
     */
    protected function runInternal(string $view, $variables = [], $forced = false, $runFast = false): string
    {
        $this->currentView = $view;
        if (@\count($this->composerStack)) {
            $this->evalComposer($view);
        }
        if (@\count($this->variablesGlobal) > 0) {
            $this->variables = \array_merge($variables, $this->variablesGlobal);
            //$this->variablesGlobal = []; // used so we delete it.
        } else {
            $this->variables = $variables;
        }
        if (!$runFast) {
            // a) if the "compile" is forced then we compile the original file, then save the file.
            // b) if the "compile" is not forced then we read the datetime of both file, and we compared.
            // c) in both cases, if the compiled doesn't exist then we compile.
            if ($view) {
                $this->fileName = $view;
            }
            $result = $this->compile($view, $forced);
            if (!$this->isCompiled) {
                return $this->postRun($this->evaluateText($result, $this->variables));
            }
        } elseif ($view) {
            $this->fileName = $view;
        }
        $this->isRunFast = $runFast;
        return $this->postRun($this->evaluatePath($this->getCompiledFile(), $this->variables));
    }

    protected function evalComposer($view): void
    {
        foreach ($this->composerStack as $viewKey => $fn) {
            if ($this->wildCardComparison($view, $viewKey)) {
                if (is_callable($fn)) {
                    $fn($this);
                } elseif ($this->methodExistsStatic($fn, 'composer')) {
                    // if the method exists statically then $fn is the class and 'composer' is the name of the method
                    $fn::composer($this);
                } elseif (is_object($fn) || class_exists($fn)) {
                    // if $fn is an object, or it is a class and the class exists.
                    $instance = (is_object($fn)) ? $fn : new $fn();
                    if (method_exists($instance, 'composer')) {
                        // and the method exists inside the instance.
                        $instance->composer($this);
                    } else {
                        if ($this->mode === self::MODE_DEBUG) {
                            $this->showError('evalComposer', "View: composer() added an incorrect method [$fn]", true, true);
                            return;
                        }
                        $this->showError('evalComposer', 'View: composer() added an incorrect method', true, true);
                        return;
                    }
                } else {
                    $this->showError('evalComposer', 'View: composer() added an incorrect method', true, true);
                }
            }
        }
    }

    /**
     * It compares with wildcards (*) and returns true if both strings are equals<br>
     * The wildcards only works at the beginning and/or at the end of the string.<br>
     * **Example:**<br>
     * ```
     * Text::wildCardComparison('abcdef','abc*'); // true
     * Text::wildCardComparison('abcdef','*def'); // true
     * Text::wildCardComparison('abcdef','*abc*'); // true
     * Text::wildCardComparison('abcdef','*cde*'); // true
     * Text::wildCardComparison('abcdef','*cde'); // false
     *
     * ```
     *
     * @param string      $text
     * @param string|null $textWithWildcard
     *
     * @return bool
     */
    protected function wildCardComparison($text, $textWithWildcard): bool
    {
        if (($textWithWildcard === null || $textWithWildcard === '')
            || strpos($textWithWildcard, '*') === false
        ) {
            // if the text with wildcard is null or empty, or it contains two ** or it contains no * then..
            return $text == $textWithWildcard;
        }
        if ($textWithWildcard === '*' || $textWithWildcard === '**') {
            return true;
        }
        $c0 = $textWithWildcard[0];
        $c1 = substr($textWithWildcard, -1);
        $textWithWildcardClean = str_replace('*', '', $textWithWildcard);
        $p0 = strpos($text, $textWithWildcardClean);
        if ($p0 === false) {
            // no matches.
            return false;
        }
        if ($c0 === '*' && $c1 === '*') {
            // $textWithWildcard='*asasasas*'
            return true;
        }
        if ($c1 === '*') {
            // $textWithWildcard='asasasas*'
            return $p0 === 0;
        }
        // $textWithWildcard='*asasasas'
        $len = strlen($textWithWildcardClean);
        return (substr($text, -$len) === $textWithWildcardClean);
    }

    protected function methodExistsStatic($class, $method): bool
    {
        try {
            return (new \ReflectionMethod($class, $method))->isStatic();
        } catch (\ReflectionException $e) {
            return false;
        }
    }

    /**
     * Compile the view at the given path.
     *
     * @param string $templateName The name of the template. Example folder.template
     * @param bool   $forced       If the compilation will be forced (always compile) or not.
     * @return boolean|string True if the operation was correct, or false (if not exception)
     *                             if it fails. It returns a string (the content compiled) if isCompiled=false
     * @throws Exception
     */
    public function compile($templateName = null, $forced = false)
    {
        $compiled = $this->getCompiledFile($templateName);
        $template = $this->getTemplateFile($templateName);
        if (!$this->isCompiled) {
            $contents = $this->compileString($this->getFile($template));
            $this->compileCallBacks($contents, $templateName);
            return $contents;
        }
        if ($forced || $this->isExpired($templateName)) {
            // compile the original file
            $contents = $this->compileString($this->getFile($template));
            $this->compileCallBacks($contents, $templateName);
            if ($this->optimize) {
                // removes space and tabs and replaces by a single space
                $contents = \preg_replace('/^ {2,}/m', ' ', $contents);
                $contents = \preg_replace('/^\t{2,}/m', ' ', $contents);
            }
            $ok = @\file_put_contents($compiled, $contents);
            if ($ok === false) {
                $this->showError(
                    'Compiling',
                    "Unable to save the file [$compiled]. Check the compile folder is defined and has the right permission"
                );
                return false;
            }
        }
        return true;
    }

    /**
     * Get the full path of the compiled file.
     *
     * @param string $templateName
     * @return string
     */
    public function getCompiledFile($templateName = ''): string
    {
        $templateName = (empty($templateName)) ? $this->fileName : $templateName;
        $fullPath = $this->getTemplateFile($templateName);
        if ($fullPath == '') {
            throw new \RuntimeException('Template not found: ' . ($this->mode == self::MODE_DEBUG ? $this->templatePath[0] . '/' . $templateName : $templateName));
        }
        $style = $this->compileTypeFileName;
        if ($style === 'auto') {
            $style = 'sha1';
        }
        $hash = $style === 'md5' ? \md5($fullPath) : \sha1($fullPath);
        return $this->compiledPath . '/' . basename($templateName) . '_' . $hash . $this->compileExtension;
    }

    /**
     * Get the mode of the engine.See View::MODE_* constants
     *
     * @return int=[self::MODE_AUTO,self::MODE_DEBUG,self::MODE_FAST,self::MODE_SLOW][$i]
     */
    public function getMode(): int
    {
        if (\defined('BLADEONE_MODE')) {
            $this->mode = BLADEONE_MODE;
        }
        return $this->mode;
    }

    /**
     * Set the compile mode<br>
     *
     * @param $mode int=[self::MODE_AUTO,self::MODE_DEBUG,self::MODE_FAST,self::MODE_SLOW][$i]
     * @return void
     */
    public function setMode($mode): void
    {
        $this->mode = $mode;
    }

    /**
     * It sets the comment mode<br>
     * @param int $commentMode =[0,1,2][$i] <br>
     *                         **0** comments are generated as php code.<br>
     *                         **1** comments are generated as html code<br>
     *                         **2** comments are ignored (no code is generated)<br>
     * @return void
     */
    public function setCommentMode(int $commentMode): void
    {
        $this->commentMode = $commentMode;
    }

    /**
     * Get the full path of the template file.
     * <p>Example: getTemplateFile('.abc.def')</p>
     *
     * @param string $templateName template name. If not template is set then it uses the base template.
     * @return string
     */
    public function getTemplateFile($templateName = ''): string
    {
        $templateName = (empty($templateName)) ? $this->fileName : $templateName;
        if (\strpos($templateName, '/') !== false) {
            return $this->locateTemplate($templateName); // it's a literal
        }
        $arr = \explode('.', $templateName);
        $c = \count($arr);
        if ($c == 1) {
            // it's in the root of the template folder.
            return $this->locateTemplate($templateName . $this->fileExtension);
        }
        $file = $arr[$c - 1];
        \array_splice($arr, $c - 1, $c - 1); // delete the last element
        $path = \implode('/', $arr);
        return $this->locateTemplate($path . '/' . $file . $this->fileExtension);
    }

    /**
     * Find template file with the given name in all template paths in the order the paths were written
     *
     * @param string $name Filename of the template (without path)
     * @return string template file
     */
    protected function locateTemplate($name): string
    {
        $this->notFoundPath = '';
        foreach ($this->templatePath as $dir) {
            $path = $dir . '/' . $name;
            if (\is_file($path)) {
                return $path;
            }
            $this->notFoundPath .= $path . ",";
        }
        return '';
    }

    /**
     * Get the contents of a file.
     *
     * @param string $fullFileName It gets the content of a filename or returns ''.
     *
     * @return string
     */
    public function getFile($fullFileName): string
    {
        if (\is_file($fullFileName)) {
            return \file_get_contents($fullFileName);
        }
        $this->showError('getFile', "File does not exist at paths (separated by comma) [$this->notFoundPath] or permission denied");
        return '';
    }

    protected function compileCallBacks(&$contents, $templateName): void
    {
        if (!empty($this->compileCallbacks)) {
            foreach ($this->compileCallbacks as $callback) {
                if (is_callable($callback)) {
                    $callback($contents, $templateName);
                }
            }
        }
    }

    /**
     * Determine if the view has expired.
     *
     * @param string|null $fileName
     * @return bool
     */
    public function isExpired($fileName): bool
    {
        $compiled = $this->getCompiledFile($fileName);
        $template = $this->getTemplateFile($fileName);
        if (!\is_file($template)) {
            if ($this->mode == self::MODE_DEBUG) {
                $this->showError('Read file', 'Template not found :' . $this->fileName . " on file: $template", true);
            } else {
                $this->showError('Read file', 'Template not found :' . $this->fileName, true);
            }
        }
        // If the compiled file doesn't exist we will indicate that the view is expired
        // so that it can be re-compiled. Else, we will verify the last modification
        // of the views is less than the modification times of the compiled views.
        if (!$this->compiledPath || !\is_file($compiled)) {
            return true;
        }
        return \filemtime($compiled) < \filemtime($template);
    }

    /**
     * Evaluates a text (string) using the current variables
     *
     * @param string $content
     * @param array  $variables
     * @return string
     * @throws Exception
     */
    protected function evaluateText($content, $variables): string
    {
        \ob_start();
        \extract($variables);
        // We'll evaluate the contents of the view inside a try/catch block, so we can
        // flush out any stray output that might get out before an error occurs or
        // an exception is thrown. This prevents any partial views from leaking.
        try {
            eval(' ?>' . $content . $this->phpTag);
        } catch (\Throwable $e) {
            $this->handleViewException($e);
        }
        return \ltrim(\ob_get_clean());
    }

    /**
     * Handle a view exception.
     *
     * @param Exception $e
     * @return void
     * @throws $e
     */
    protected function handleViewException($e): void
    {
        \ob_get_clean();
        throw $e;
    }

    /**
     * Evaluates a compiled file using the current variables
     *
     * @param string $compiledFile full path of the compile file.
     * @param array  $variables
     * @return string
     * @throws Exception
     */
    protected function evaluatePath($compiledFile, $variables): string
    {
        \ob_start();
        // note, the variables are extracted locally inside this method,
        // they are not global variables :-3
        \extract($variables);
        // We'll evaluate the contents of the view inside a try/catch block, so we can
        // flush out any stray output that might get out before an error occurs or
        // an exception is thrown. This prevents any partial views from leaking.
        try {
            include $compiledFile;
        } catch (\Throwable $e) {
            $this->handleViewException($e);
        }
        return \ltrim(\ob_get_clean());
    }

    /**
     * @param array $views array of views
     * @param array $value
     * @return string
     * @throws Exception
     */
    public function includeFirst($views = [], $value = []): string
    {
        foreach ($views as $view) {
            if ($this->templateExist($view)) {
                return $this->runChild($view, $value);
            }
        }
        return '';
    }

    /**
     * Returns true if the template exists. Otherwise, it returns false
     *
     * @param $templateName
     * @return bool
     */
    protected function templateExist($templateName): bool
    {
        $file = $this->getTemplateFile($templateName);
        return \is_file($file);
    }

    /**
     * Convert an array such as ["class1"=>"myclass","style="mystyle"] to class1='myclass' style='mystyle' string
     *
     * @param array|string $array array to convert
     * @return string
     */
    public function convertArg($array): string
    {
        if (!\is_array($array)) {
            return $array;  // nothing to convert.
        }
        return \implode(' ', \array_map('View::convertArgCallBack', \array_keys($array), $array));
    }

    /**
     * Returns the current token. if there is not a token then it generates a new one.
     * It could require an open session.
     *
     * @param bool   $fullToken It returns a token with the current ip.
     * @param string $tokenId   [optional] Name of the token.
     *
     * @return string
     */
    public function getCsrfToken($fullToken = false, $tokenId = '_token'): string
    {
        if ($this->csrf_token == '') {
            $this->regenerateToken($tokenId);
        }
        if ($fullToken) {
            return $this->csrf_token . '|' . $this->ipClient();
        }
        return $this->csrf_token;
    }

    /**
     * Regenerates the csrf token and stores in the session.
     * It requires an open session.
     *
     * @param string $tokenId [optional] Name of the token.
     */
    public function regenerateToken($tokenId = '_token'): void
    {
        try {
            $this->csrf_token = \bin2hex(\random_bytes(10));
        } catch (\Throwable $e) {
            $this->csrf_token = '123456789012345678901234567890'; // unable to generates a random token.
        }
        @$_SESSION[$tokenId] = $this->csrf_token . '|' . $this->ipClient();
    }

    public function ipClient()
    {
        if (
            isset($_SERVER['HTTP_X_FORWARDED_FOR'])
            && \preg_match('/^(d{1,3}).(d{1,3}).(d{1,3}).(d{1,3})$/', $_SERVER['HTTP_X_FORWARDED_FOR'])
        ) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * Validates if the csrf token is valid or not.<br>
     * It requires an open session.
     *
     * @param bool   $alwaysRegenerate [optional] Default is false.<br>
     *                                 If **true** then it will generate a new token regardless
     *                                 of the method.<br>
     *                                 If **false**, then it will generate only if the method is POST.<br>
     *                                 Note: You must not use true if you want to use csrf with AJAX.
     *
     * @param string $tokenId          [optional] Name of the token.
     *
     * @return bool It returns true if the token is valid, or it is generated. Otherwise, false.
     */
    public function csrfIsValid($alwaysRegenerate = false, $tokenId = '_token'): bool
    {
        if (@$_SERVER['REQUEST_METHOD'] === 'POST' && $alwaysRegenerate === false) {
            $this->csrf_token = $_POST[$tokenId] ?? null; // ping pong the token.
            return $this->csrf_token . '|' . $this->ipClient() === ($_SESSION[$tokenId] ?? null);
        }
        if ($this->csrf_token == '' || $alwaysRegenerate) {
            // if not token then we generate a new one
            $this->regenerateToken($tokenId);
        }
        return true;
    }

    /**
     * Stop injecting content into a section and return its contents.
     *
     * @return string
     */
    public function yieldSection(): ?string
    {
        $sc = $this->stopSection();
        return $this->sections[$sc] ?? null;
    }

    /**
     * Stop injecting content into a section.
     *
     * @param bool $overwrite
     * @return string
     */
    public function stopSection($overwrite = false): string
    {
        if (empty($this->sectionStack)) {
            $this->showError('stopSection', 'Cannot end a section without first starting one.', true, true);
        }
        $last = \array_pop($this->sectionStack);
        if ($overwrite) {
            $this->sections[$last] = \ob_get_clean();
        } else {
            $this->extendSection($last, \ob_get_clean());
        }
        return $last;
    }

    /**
     * Append content to a given section.
     *
     * @param string $section
     * @param string $content
     * @return void
     */
    protected function extendSection($section, $content): void
    {
        if (isset($this->sections[$section])) {
            $content = \str_replace($this->PARENTKEY, $content, $this->sections[$section]);
        }
        $this->sections[$section] = $content;
    }

    /**
     * @param mixed $object
     * @param bool  $jsconsole
     * @return void
     * @throws \JsonException
     */
    public function dump($object, bool $jsconsole = false): void
    {
        if (!$jsconsole) {
            echo '<pre>';
            \var_dump($object);
            echo '</pre>';
        } else {
            /** @noinspection BadExpressionStatementJS */
            /** @noinspection JSVoidFunctionReturnValueUsed */
            echo '<script>console.log(' . \json_encode($object, JSON_THROW_ON_ERROR) . ')</script>';
        }
    }

    /**
     * Start injecting content into a section.
     *
     * @param string $section
     * @param string $content
     * @return void
     */
    public function startSection($section, $content = ''): void
    {
        if ($content === '') {
            \ob_start() && $this->sectionStack[] = $section;
        } else {
            $this->extendSection($section, $content);
        }
    }

    /**
     * Stop injecting content into a section and append it.
     *
     * @return string
     * @throws InvalidArgumentException
     */
    public function appendSection(): string
    {
        if (empty($this->sectionStack)) {
            $this->showError('appendSection', 'Cannot end a section without first starting one.', true, true);
        }
        $last = \array_pop($this->sectionStack);
        if (isset($this->sections[$last])) {
            $this->sections[$last] .= \ob_get_clean();
        } else {
            $this->sections[$last] = \ob_get_clean();
        }
        return $last;
    }

    /**
     * Adds a global variable. If **$varname** is an array then it merges all the values.
     * **Example:**
     * ```
     * $this->share('variable',10.5);
     * $this->share('variable2','hello');
     * // or we could add the two variables as:
     * $this->share(['variable'=>10.5,'variable2'=>'hello']);
     * ```
     *
     * @param string|array $varname It is the name of the variable or, it is an associative array
     * @param mixed        $value
     * @return $this
     * @see View::share
     */
    public function with($varname, $value = null): View
    {
        return $this->share($varname, $value);
    }

    /**
     * Adds a global variable. If **$varname** is an array then it merges all the values.
     * **Example:**
     * ```
     * $this->share('variable',10.5);
     * $this->share('variable2','hello');
     * // or we could add the two variables as:
     * $this->share(['variable'=>10.5,'variable2'=>'hello']);
     * ```
     *
     * @param string|array $varname It is the name of the variable, or it is an associative array
     * @param mixed        $value
     * @return $this
     */
    public function share($varname, $value = null): View
    {
        if (is_array($varname)) {
            $this->variablesGlobal = \array_merge($this->variablesGlobal, $varname);
        } else {
            $this->variablesGlobal[$varname] = $value;
        }
        return $this;
    }

    /**
     * Get the string contents of a section.
     *
     * @param string $section
     * @param string $default
     * @return string
     */
    public function yieldContent($section, $default = ''): string
    {
        if (isset($this->sections[$section])) {
            return \str_replace($this->PARENTKEY, $default, $this->sections[$section]);
        }
        return $default;
    }

    /**
     * Register a custom Blade compiler.
     *
     * @param callable $compiler
     * @return void
     */
    public function extend(callable $compiler): void
    {
        $this->extensions[] = $compiler;
    }

    /**
     * Register a handler for custom directives for run at runtime
     *
     * @param string   $name
     * @param callable $handler
     * @return void
     */
    public function directiveRT($name, callable $handler): void
    {
        $this->customDirectives[$name] = $handler;
        $this->customDirectivesRT[$name] = true;
    }

    /**
     * Sets the escaped content tags used for the compiler.
     *
     * @param string $openTag
     * @param string $closeTag
     * @return void
     */
    public function setEscapedContentTags($openTag, $closeTag): void
    {
        $this->setContentTags($openTag, $closeTag, true);
    }

    /**
     * Gets the content tags used for the compiler.
     *
     * @return array
     */
    public function getContentTags(): array
    {
        return $this->getTags();
    }

    /**
     * Sets the content tags used for the compiler.
     *
     * @param string $openTag
     * @param string $closeTag
     * @param bool   $escaped
     * @return void
     */
    public function setContentTags($openTag, $closeTag, $escaped = false): void
    {
        $property = ($escaped === true) ? 'escapedTags' : 'contentTags';
        $this->{$property} = [\preg_quote($openTag), \preg_quote($closeTag)];
    }

    /**
     * Gets the tags used for the compiler.
     *
     * @param bool $escaped
     * @return array
     */
    protected function getTags($escaped = false): array
    {
        $tags = $escaped ? $this->escapedTags : $this->contentTags;
        return \array_map('stripcslashes', $tags);
    }

    /**
     * Gets the escaped content tags used for the compiler.
     *
     * @return array
     */
    public function getEscapedContentTags(): array
    {
        return $this->getTags(true);
    }

    /**
     * Sets the function used for resolving classes with inject.
     *
     * @param callable $function
     */
    public function setInjectResolver(callable $function): void
    {
        $this->injectResolver = $function;
    }

    /**
     * Get the file extension for template files.
     *
     * @return string
     */
    public function getFileExtension(): string
    {
        return $this->fileExtension;
    }

    /**
     * Set the file extension for the template files.
     * It must include the leading dot e.g. ".blade.php"
     *
     * @param string $fileExtension Example: .prefix.ext
     */
    public function setFileExtension($fileExtension): void
    {
        $this->fileExtension = $fileExtension;
    }

    /**
     * Get the file extension for template files.
     *
     * @return string
     */
    public function getCompiledExtension(): string
    {
        return $this->compileExtension;
    }

    /**
     * Set the file extension for the compiled files.
     * Including the leading dot for the extension is required, e.g. ".bladec"
     *
     * @param $fileExtension
     */
    public function setCompiledExtension($fileExtension): void
    {
        $this->compileExtension = $fileExtension;
    }

    /**
     * @return string
     * @see View::setCompileTypeFileName
     */
    public function getCompileTypeFileName(): string
    {
        return $this->compileTypeFileName;
    }

    /**
     * It determines how the compiled filename will be called.<br>
     * * **auto** (default mode) the mode is "sha1"<br>
     * * **sha1** the filename is converted into a sha1 hash (it's the slow method, but it is safest)<br>
     * * **md5** the filename is converted into a md5 hash (it's faster than sha1, and it uses less space)<br>
     * @param string $compileTypeFileName =['auto','sha1','md5'][$i]
     * @return View
     */
    public function setCompileTypeFileName(string $compileTypeFileName): View
    {
        $this->compileTypeFileName = $compileTypeFileName;
        return $this;
    }

    /**
     * Add new loop to the stack.
     *
     * @param array|Countable $data
     * @return void
     */
    public function addLoop($data): void
    {
        $length = \is_countable($data) || $data instanceof Countable ? \count($data) : null;
        $parent = static::last($this->loopsStack);
        $this->loopsStack[] = [
            'index' => -1,
            'iteration' => 0,
            'remaining' => isset($length) ? $length + 1 : null,
            'count' => $length,
            'first' => true,
            'even' => true,
            'odd' => false,
            'last' => isset($length) ? $length == 1 : null,
            'depth' => \count($this->loopsStack) + 1,
            'parent' => $parent ? (object)$parent : null,
        ];
    }

    /**
     * Increment the top loop's indices.
     *
     * @return object
     */
    public function incrementLoopIndices(): object
    {
        $c = \count($this->loopsStack) - 1;
        $loop = &$this->loopsStack[$c];
        $loop['index']++;
        $loop['iteration']++;
        $loop['first'] = $loop['index'] == 0;
        $loop['even'] = $loop['index'] % 2 == 0;
        $loop['odd'] = !$loop['even'];
        if (isset($loop['count'])) {
            $loop['remaining']--;
            $loop['last'] = $loop['index'] == $loop['count'] - 1;
        }
        return (object)$loop;
    }

    /**
     * Pop a loop from the top of the loop stack.
     *
     * @return void
     */
    public function popLoop(): void
    {
        \array_pop($this->loopsStack);
    }

    /**
     * Get an instance of the first loop in the stack.
     *
     * @return object|null
     */
    public function getFirstLoop(): ?object
    {
        return ($last = static::last($this->loopsStack)) ? (object)$last : null;
    }

    /**
     * Get the rendered contents of a partial from a loop.
     *
     * @param string $view
     * @param array  $data
     * @param string $iterator
     * @param string $empty
     * @return string
     * @throws Exception
     */
    public function renderEach($view, $data, $iterator, $empty = 'raw|'): string
    {
        $result = '';
        if (\count($data) > 0) {
            // If is actually data in the array, we will loop through the data and append
            // an instance of the partial view to the final result HTML passing in the
            // iterated value of this data array, allowing the views to access them.
            foreach ($data as $key => $value) {
                $data = ['key' => $key, $iterator => $value];
                $result .= $this->runChild($view, $data);
            }
        } elseif (static::startsWith($empty, 'raw|')) {
            $result = \substr($empty, 4);
        } else {
            $result = $this->run($empty);
        }
        return $result;
    }

    /**
     * Run the blade engine. It returns the result of the code.
     *
     * @param string|null $view      The name of the cache. Ex: "folder.folder.view" ("/folder/folder/view.blade")
     * @param array       $variables An associative arrays with the values to display.
     * @return string
     * @throws Exception
     */
    public function run($view = null, $variables = []): string
    {
        $mode = $this->getMode();
        if ($view === null) {
            $view = $this->viewStack;
        }
        $this->viewStack = null;
        if ($view === null) {
            $this->showError('run', 'View: view not set', true);
            return '';
        }
        $forced = ($mode & 1) !== 0; // mode=1 forced:it recompiles no matter if the compiled file exists or not.
        $runFast = ($mode & 2) !== 0; // mode=2 runfast: the code is not compiled neither checked, and it runs directly the compiled
        $this->sections = [];
        if ($mode == 3) {
            $this->showError('run', "we can't force and run fast at the same time", true);
        }
        return $this->runInternal($view, $variables, $forced, $runFast);
    }

    /**
     * It executes a post run execution. It is used to display the stacks.
     * @noinspection PhpVariableIsUsedOnlyInClosureInspection
     */
    protected function postRun(?string $string)
    {
        if (!$string) {
            return $string;
        }
        if (strpos($string, $this->escapeStack0) === false) {
            // nothing to post run
            return $string;
        }
        $me = $this;
        // we returned the escape character.
        return preg_replace_callback('/' . $this->escapeStack0 . '\s?([A-Za-z0-9_:() ,*.@$]+)\s?' . $this->escapeStack1 . '/u',
            static function($matches) use ($me) {
                $l0 = strlen($me->escapeStack0);
                $l1 = strlen($me->escapeStack1);
                $item = trim(is_array($matches) ? substr($matches[0], $l0, -$l1) : substr($matches, $l0, -$l1));
                $items = explode(',', $item);
                return $me->yieldPushContent($items[0], $items[1] ?? null);
                //return is_array($r) ? $flagtxt . json_encode($r) : $flagtxt . $r;
            }, $string);
    }

    /**
     * It sets the current view<br>
     * This value is cleared when it is used (method run).<br>
     * **Example:**<br>
     * ```
     * $this->setView('folder.view')->share(['var1'=>20])->run(); // or $this->run('folder.view',['var1'=>20]);
     * ```
     *
     * @param string $view
     * @return View
     */
    public function setView($view): View
    {
        $this->viewStack = $view;
        return $this;
    }

    /**
     * It injects a function, an instance, or a method class when a view is called.<br>
     * It could be stacked.   If it sets null then it clears all definitions.
     * **Example:**<br>
     * ```
     * $this->composer('folder.view',function($bladeOne) { $bladeOne->share('newvalue','hi there'); });
     * $this->composer('folder.view','namespace1\namespace2\SomeClass'); // SomeClass must exist, and it must have the
     *                                                                   // method 'composer'
     * $this->composer('folder.*',$instance); // $instance must have the method called 'composer'
     * $this->composer(); // clear all composer.
     * ```
     *
     * @param string|array|null    $view It could contain wildcards (*). Example:
     *                                   'aa.bb.cc','*.bb.cc','aa.bb.*','*.bb.*'
     *
     * @param callable|string|null $functionOrClass
     * @return View
     */
    public function composer($view = null, $functionOrClass = null): View
    {
        if ($view === null && $functionOrClass === null) {
            $this->composerStack = [];
            return $this;
        }
        if (is_array($view)) {
            foreach ($view as $v) {
                $this->composerStack[$v] = $functionOrClass;
            }
        } else {
            $this->composerStack[$view] = $functionOrClass;
        }
        return $this;
    }

    /**
     * Start a component rendering process.
     *
     * @param string $name
     * @param array  $data
     * @return void
     */
    public function startComponent($name, array $data = []): void
    {
        if (\ob_start()) {
            $this->componentStack[] = $name;
            $this->componentData[$this->currentComponent()] = $data;
            $this->slots[$this->currentComponent()] = [];
        }
    }

    /**
     * Get the index for the current component.
     *
     * @return int
     */
    protected function currentComponent(): int
    {
        return \count($this->componentStack) - 1;
    }

    /**
     * Render the current component.
     *
     * @return string
     * @throws Exception
     */
    public function renderComponent(): string
    {
        //echo "<hr>render<br>";
        $name = \array_pop($this->componentStack);
        //return $this->runChild($name, $this->componentData());
        $cd = $this->componentData();
        $clean = array_keys($cd);
        $r = $this->runChild($name, $cd);
        // we clean variables defined inside the component (so they are garbaged when the component is used)
        foreach ($clean as $key) {
            unset($this->variables[$key]);
        }
        return $r;
    }

    /**
     * Get the data for the given component.
     *
     * @return array
     */
    protected function componentData(): array
    {
        $cs = count($this->componentStack);
        //echo "<hr>";
        //echo "<br>data:<br>";
        //var_dump($this->componentData);
        //echo "<br>datac:<br>";
        //var_dump(count($this->componentStack));
        return array_merge(
            $this->componentData[$cs],
            ['slot' => trim(ob_get_clean())],
            $this->slots[$cs]
        );
    }

    /**
     * Start the slot rendering process.
     *
     * @param string      $name
     * @param string|null $content
     * @return void
     */
    public function slot($name, $content = null): void
    {
        if (\count(\func_get_args()) === 2) {
            $this->slots[$this->currentComponent()][$name] = $content;
        } elseif (\ob_start()) {
            $this->slots[$this->currentComponent()][$name] = '';
            $this->slotStack[$this->currentComponent()][] = $name;
        }
    }

    /**
     * Save the slot content for rendering.
     *
     * @return void
     */
    public function endSlot(): void
    {
        static::last($this->componentStack);
        $currentSlot = \array_pop(
            $this->slotStack[$this->currentComponent()]
        );
        $this->slots[$this->currentComponent()][$currentSlot] = \trim(\ob_get_clean());
    }

    /**
     * @return string
     */
    public function getPhpTag(): string
    {
        return $this->phpTag;
    }

    /**
     * @param string $phpTag
     */
    public function setPhpTag($phpTag): void
    {
        $this->phpTag = $phpTag;
    }

    /**
     * @return string
     */
    public function getCurrentUser(): string
    {
        return $this->currentUser;
    }

    /**
     * @param string $currentUser
     */
    public function setCurrentUser($currentUser): void
    {
        $this->currentUser = $currentUser;
    }

    /**
     * @return string
     */
    public function getCurrentRole(): string
    {
        return $this->currentRole;
    }

    /**
     * @param string $currentRole
     */
    public function setCurrentRole($currentRole): void
    {
        $this->currentRole = $currentRole;
    }

    /**
     * @return string[]
     */
    public function getCurrentPermission(): array
    {
        return $this->currentPermission;
    }

    /**
     * @param string[] $currentPermission
     */
    public function setCurrentPermission($currentPermission): void
    {
        $this->currentPermission = $currentPermission;
    }

    /**
     * Returns the current base url without trailing slash.
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * It sets the base url and, it also calculates the relative path.<br>
     * The base url defines the "root" of the project, not always the level of the domain, but it could be
     * any folder.<br>
     * This value is used to calculate the relativity of the resources, but it is also used to set the domain.<br>
     * **Note:** The trailing slash is removed automatically if it's present.<br>
     * **Note:** We should not use arguments or name of the script.<br>
     * **Examples:**<br>
     * ```
     * $this->setBaseUrl('http://domain.dom/myblog');
     * $this->setBaseUrl('http://domain.dom/corporate/erp');
     * $this->setBaseUrl('http://domain.dom/blog.php?args=20'); // avoid this one.
     * $this->setBaseUrl('http://another.dom');
     * ```
     *
     * @param string $baseUrl Example http://www.web.com/folder  https://www.web.com/folder/anotherfolder
     * @return View
     */
    public function setBaseUrl(string $baseUrl): View
    {
        $this->baseUrl = \rtrim($baseUrl, '/'); // base with the url trimmed
        $this->baseDomain = @parse_url($this->baseUrl)['host'];
        $currentUrl = $this->getCurrentUrlCalculated();
        if ($currentUrl === '') {
            $this->relativePath = '';
            return $this;
        }
        if (\strpos($currentUrl, $this->baseUrl) === 0) {
            $part = \str_replace($this->baseUrl, '', $currentUrl);
            $numf = \substr_count($part, '/') - 1;
            $numf = ($numf > 10) ? 10 : $numf; // avoid overflow
            $this->relativePath = ($numf < 0) ? '' : \str_repeat('../', $numf);
        } else {
            $this->relativePath = '';
        }
        return $this;
    }

    /**
     * It sets a CDN Url used by @assetcdn("someresource.jpg")<br>
     * **Example:**
     * ```
     * $this->setCDNUrl('http://domain.dom/myblog');
     * ```
     *
     * @param string $cdnurl the full path url without the trailing slash
     * @return $this
     */
    public function setCDNUrl(string $cdnurl): View
    {
        $this->cdnUrl = $cdnurl;
        return $this;
    }

    /**
     * It gets the full current url calculated with the information sends by the user.<br>
     * **Note:** If we set baseurl, then it always uses the baseurl as domain (it's safe).<br>
     * **Note:** This information could be forged/faked by the end-user.<br>
     * **Note:** It returns empty '' if it is called in a command line interface / non-web.<br>
     * **Note:** It doesn't return the user and password.<br>
     * @param bool $noArgs if true then it excludes the arguments.
     * @return string
     */
    public function getCurrentUrlCalculated($noArgs = false): string
    {
        if (!isset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'])) {
            return '';
        }
        $host = $this->baseDomain ?? $_SERVER['HTTP_HOST']; // <-- it could be forged!
        $link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
        $port = $_SERVER['SERVER_PORT'];
        $port2 = (($link === 'http' && $port === '80') || ($link === 'https' && $port === '443')) ? '' : ':' . $port;
        $link .= "://$host$port2$_SERVER[REQUEST_URI]";
        if ($noArgs) {
            $link = @explode('?', $link)[0];
        }
        return $link;
    }

    /**
     * It returns the relative path to the base url or empty if not set<br>
     * **Example:**<br>
     * ```
     * // current url='http://domain.dom/page/subpage/web.php?aaa=2
     * $this->setBaseUrl('http://domain.dom/');
     * $this->getRelativePath(); // '../../'
     * $this->setBaseUrl('http://domain.dom/');
     * $this->getRelativePath(); // '../../'
     * ```
     * **Note:**The relative path is calculated when we set the base url.
     *
     * @return string
     * @see View::setBaseUrl
     */
    public function getRelativePath(): string
    {
        return $this->relativePath;
    }

    /**
     * It gets the full current canonical url.<br>
     * **Example:** https://www.mysite.com/aaa/bb/php.php?aa=bb
     * <ul>
     * <li>It returns the $this->canonicalUrl value if is not null</li>
     * <li>Otherwise, it returns the $this->currentUrl if not null</li>
     * <li>Otherwise, the url is calculated with the information sends by the user</li>
     * </ul>
     *
     * @return string|null
     */
    public function getCanonicalUrl(): ?string
    {
        return $this->canonicalUrl ?? $this->getCurrentUrl();
    }

    /**
     * It sets the full canonical url.<br>
     * **Example:** https://www.mysite.com/aaa/bb/php.php?aa=bb
     *
     * @param string|null $canonUrl
     * @return View
     */
    public function setCanonicalUrl($canonUrl = null): View
    {
        $this->canonicalUrl = $canonUrl;
        return $this;
    }

    /**
     * It gets the full current url<br>
     * **Example:** https://www.mysite.com/aaa/bb/php.php?aa=bb
     * <ul>
     * <li>It returns the $this->currentUrl if not null</li>
     * <li>Otherwise, the url is calculated with the information sends by the user</li>
     * </ul>
     *
     * @param bool $noArgs if true then it ignores the arguments.
     * @return string|null
     */
    public function getCurrentUrl($noArgs = false): ?string
    {
        $link = $this->currentUrl ?? $this->getCurrentUrlCalculated();
        if ($noArgs) {
            $link = @explode('?', $link)[0];
        }
        return $link;
    }

    /**
     * It sets the full current url.<br>
     * **Example:** https://www.mysite.com/aaa/bb/php.php?aa=bb
     * **Note:** If the current url is not set, then the system could calculate the current url.
     *
     * @param string|null $currentUrl
     * @return View
     */
    public function setCurrentUrl($currentUrl = null): View
    {
        $this->currentUrl = $currentUrl;
        return $this;
    }

    /**
     * If true then it optimizes the result (it removes tab and extra spaces).
     *
     * @param bool $bool
     * @return View
     */
    public function setOptimize($bool = false): View
    {
        $this->optimize = $bool;
        return $this;
    }

    /**
     * It sets the callback function for authentication. It is used by @can and @cannot
     *
     * @param callable $fn
     */
    public function setCanFunction(callable $fn): void
    {
        $this->authCallBack = $fn;
    }

    /**
     * It sets the callback function for authentication. It is used by @canany
     *
     * @param callable $fn
     */
    public function setAnyFunction(callable $fn): void
    {
        $this->authAnyCallBack = $fn;
    }

    /**
     * It sets the callback function for errors. It is used by @error
     *
     * @param callable $fn
     */
    public function setErrorFunction(callable $fn): void
    {
        $this->errorCallBack = $fn;
    }

    /**
     * Resolve a given class using the injectResolver callable.
     *
     * @param string      $className
     * @param string|null $variableName
     * @return mixed
     */
    protected function injectClass($className, $variableName = null)
    {
        if (isset($this->injectResolver)) {
            return call_user_func($this->injectResolver, $className, $variableName);
        }
        $fullClassName = $className . "\\" . $variableName;
        return new $fullClassName();
    }

    /**
     * Used for @_e directive.
     *
     * @param $expression
     *
     * @return string
     */
    protected function compile_e($expression): string
    {
        return $this->phpTagEcho . "\$this->_e$expression; ?>";
    }

    /**
     * Used for @_ef directive.
     *
     * @param $expression
     *
     * @return string
     */
    protected function compile_ef($expression): string
    {
        return $this->phpTagEcho . "\$this->_ef$expression; ?>";
    }
}