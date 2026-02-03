<?php
namespace Sura\View;

use ArrayAccess;
use BadMethodCallException;
use Closure;
use Countable;
use Exception;
use InvalidArgumentException;

/**
 * View - A Blade Template implementation in a single file
 *
 * @package   View
 * @author    Jorge Patricio Castro Castillo <jcastro arroba eftec dot cl>
 * @copyright Copyright (c) 2016-2025 Jorge Patricio Castro Castillo MIT License.
 *            Don't delete this comment, its part of the license.
 *            Part of this code is based on the work of Laravel PHP Components.
 * @version   3.0.0
 * @link      https://github.com/EFTEC/View
 */
class View
{
    use Compilers\CompilesArray,
        Compilers\CompilesAtributes,
        Compilers\CompilesCli,
        Compilers\CompilesCommon,
        Compilers\CompilesCompile,
        Compilers\CompilesExtras,
        Compilers\CompilesFile,
        Compilers\CompilesLanguage,
        Compilers\CompilesLoop,
        Compilers\CompilesPush,
        Compilers\CompilesSetGet,
        Compilers\CompilesSvg,
        Compilers\CompilesString;

    public const VERSION = '3.0.0';
    /** @var int View reads if the compiled file has changed. If it has changed, then the file is replaced. */
    public const MODE_AUTO = 0;
    /** @var int The compiled file is always replaced. It's slow and it's useful for development. */
    public const MODE_SLOW = 1;
    /** @var int The compiled file is never replaced. It's fast and it's useful for production. */
    public const MODE_FAST = 2;
    /** @var int DEBUG MODE, the file is always compiled and the filename is identifiable. */
    public const MODE_DEBUG = 5;
    /** @var array Hold dictionary of translations */
    public static array $dictionary = [];
    /** @var string It is used to mark the start of the stack (regexp). This value must not be used for other purposes */
    public string $escapeStack0 = '-#1Z#-#2B#';
    /** @var string It is used to mark the end of the stack (regexp). This value must not be used for other purposes */
    public string $escapeStack1 = '#3R#-#4X#-';
    /** @var string PHP tag. You could use < ?php or < ? (if shorttag is active in php.ini) */
    public string $phpTag = '<?php ';
    /** @var string this line is used to easily echo a value */
    protected string $phpTagEcho = '<?php' . ' echo ';
    /** @var string|null $currentUser Current user. Example: john */
    public ?string $currentUser;
    /** @var string|null $currentRole Current role. Example: admin */
    public ?string $currentRole;
    /** @var string[]|null $currentPermission Current permission. Example ['edit','add'] */
    public ?array $currentPermission = [];
    /** @var callable|null callback of validation. It is used for "@can,@cannot" */
    public $authCallBack;
    /** @var callable|null callback of validation. It is used for @canany */
    public $authAnyCallBack;
    /** @var callable|null callback of errors. It is used for @error */
    public $errorCallBack;
    /** @var bool if true then, if the operation fails, and it is critic, then it throws an error */
    public bool $throwOnError = false;
    /** @var string security token */
    public string $csrf_token = '';
    /** @var string The path to the missing translations log file. If empty, then every missing key is not saved. */
    public string $missingLog = '';
    /** @var bool if true then pipes commands are available, example {{$a1|strtolower}} */
    public bool $pipeEnable = false;
    /** @var array Alias (with or without namespace) of the classes */
    public array $aliasClasses = [];
    protected array $hierarcy = [];
    /**
     * @var callable[] associative array with the callable methods. The key must be the name of the method<br>
     *                 **example:**<br>
     *                 ```
     *                 $this->methods['compileAlert']=static function(?string $expression=null) { return };
     *                 $this->methods['runtimeAlert']=function(?array $arguments=null) { return };
     *                 ```
     */
    protected array $methods = [];
    protected array $controlStack = [['name' => '', 'args' => [], 'parent' => 0]];
    protected int $controlStackParent = 0;
    /** @var View it is used to get the last instance */
    public static View $instance;
    /**
     * @var bool if it is true, then the variables defined in the "include" as arguments are scoped to work only
     * inside the "include" statement.<br>
     * If false (default value), then the variables defined in the "include" as arguments are defined globally.<br>
     * <b>Example: (includeScope=false)</b><br>
     * include("template",['a1'=>'abc']) // a1 is equals to abc<br>
     * include("template",[]) // a1 is equals to abc<br>
     * <br><b>Example: (includeScope=true)</b><br>
     * include("template",['a1'=>'abc']) // a1 is equals to abc<br>
     * include("template",[]) // a1 is not defined<br>
     */
    public bool $includeScope = false;
    /**
     * @var callable[] It allows to parse the compiled output using a function.
     *      This function doesn't require to return a value<br>
     *      **Example:** this converts all compiled result in uppercase (note, content is a ref)
     *      ```
     *      $this->compileCallbacks[]= static function (&$content, $templatename=null) {
     *      $content=strtoupper($content);
     *      };
     *      ```
     */
    public array $compileCallbacks = [];
    /** @var array All the registered extensions. */
    protected array $extensions = [];
    /** @var array All the finished, captured sections. */
    protected array $sections = [];
    /** @var string The template currently being compiled. For example "folder.template" */
    protected string $fileName = "";
    protected string $currentView = "";
    protected string $notFoundPath = "";
    /** @var string File extension for the template files. */
    protected string $fileExtension = '.blade.php';
    /** @var array The stack of in-progress sections. */
    protected array $sectionStack = [];
    /** @var array The stack of in-progress loops. */
    protected array $loopsStack = [];
    /** @var array Dictionary of variables */
    protected array $variables = [];
    /** @var array Dictionary of global variables */
    protected array $variablesGlobal = [];
    /** @var array All the available compiler functions. */
    protected array $compilers = [
        'Extensions',
        'Components',
        'Statements',
        'Comments',
        'Echos',
    ];
    /** @var string|null it allows to set the stack */
    protected ?string $viewStack = null;
    /** @var array used by $this->composer() */
    protected array $composerStack = [];
    /** @var array The stack of in-progress push sections. */
    protected array $pushStack = [];
    /** @var array All the finished, captured push sections. */
    protected array $pushes = [];
    /** @var int The number of active rendering operations. */
    protected int $renderCount = 0;
    /** @var string[] Get the template path for the compiled views. */
    protected array $templatePath = [];
    /** @var string|null Get the compiled path for the compiled views. If null then it uses the default path */
    protected ?string $compiledPath = null;
    /** @var string the extension of the compiled file. */
    protected string $compileExtension = '.bladec';
    /**
     * @var string=['auto','sha1','md5'][$i] It determines how the compiled filename will be called.<br>
     *            **auto** (default mode) the mode is "sha1"<br>
     *            **sha1** the filename is converted into a sha1 hash<br>
     *            **md5** the filename is converted into a md5 hash<br>
     */
    protected string $compileTypeFileName = 'auto';
    /** @var array Custom "directive" dictionary. Those directives run at compile time. */
    protected array $customDirectives = [];
    /** @var bool[] Custom directive dictionary. Those directives run at runtime. */
    protected array $customDirectivesRT = [];
    /** @var callable Function used for resolving injected classes. */
    protected $injectResolver;
    /** @var array Used for conditional if. */
    protected array $conditions = [];
    /** @var int Unique counter. It's used for extends */
    protected int $uidCounter = 0;
    /** @var string The main url of the system. Don't use raw $_SERVER values unless the value is sanitized */
    protected string $baseUrl = '.';
    protected string $cdnUrl = '.';
    /** @var string|null The base domain of the system */
    protected ?string $baseDomain;
    /** @var string|null It stores the current canonical url. */
    protected ?string $canonicalUrl;
    /** @var string|null It stores the current url including arguments */
    protected ?string $currentUrl;
    /** @var string it is a relative path calculated between baseUrl and the current url. Example ../../ */
    protected string $relativePath = '';
    /** @var string[] Dictionary of assets */
    protected array $assetDict = [];
    protected array $assetDictCDN = [];
    /** @var bool if true then it removes tabs and unneeded spaces */
    protected bool $optimize = true;
    /** @var bool if false, then the template is not compiled (but executed on memory). */
    protected bool $isCompiled = true;
    /** @var bool */
    protected bool $isRunFast = false; // stored for historical purpose.
    /** @var array Array of opening and closing tags for raw echos. */
    protected array $rawTags = ['{!!', '!!}'];
    /** @var array Array of opening and closing tags for regular echos. */
    protected array $contentTags = ['{{', '}}'];
    protected int $commentMode = 0;
    /** @var array Array of opening and closing tags for escaped echos. */
    protected array $escapedTags = ['{{{', '}}}'];
    /** @var string The "regular" / legacy echo string format. */
    protected string $echoFormat = '\htmlentities(%s??\'\', ENT_QUOTES, \'UTF-8\', false)';
    /** @var string */
    protected string $echoFormatOld = 'static::e(%s)';
    /** @var array Lines that will be added at the footer of the template */
    protected array $footer = [];
    /** @var string Placeholder to temporary mark the position of verbatim blocks. */
    protected string $verbatimPlaceholder = '$__verbatim__$';
    /** @var array Array to temporary store the verbatim blocks found in the template. */
    protected array $verbatimBlocks = [];
    /** @var int Counter to keep track of nested forelse statements. */
    protected int $forelseCounter = 0;
    /** @var array The components being rendered. */
    protected array $componentStack = [];
    /** @var array The original data passed to the component. */
    protected array $componentData = [];
    /** @var array The slot contents for the component. */
    protected array $slots = [];
    /** @var array The names of the slots being rendered. */
    protected array $slotStack = [];
    /** @var string tag unique */
    protected string $PARENTKEY = '@parentXYZABC';
    /**
     * Indicates the compile mode.
     * if the constant BLADEONE_MODE is defined, then it is used instead of this field.
     *
     * @var int=[View::MODE_AUTO,View::MODE_DEBUG,View::MODE_SLOW,View::MODE_FAST][$i]
     */
    protected int $mode;
    /** @var int Indicates the number of open switches */
    protected int $switchCount = 0;
    /** @var bool Indicates if the switch is recently open */
    protected bool $firstCaseInSwitch = true;

    /**
     * It creates an instance of View. The folder at $compiledPath is created in case it doesn't exist.<br>
     * **Example**
     * ```
     * $blade=new View("pathtemplate","pathcompile",View::MODE_AUTO,2);
     * ```
     *
     * @param string|null $templatePath If null then it uses (caller_folder)/views
     * @param string|null $compiledPath If null then it uses (caller_folder)/compiles
     * @param int         $mode         =[View::MODE_AUTO,View::MODE_DEBUG,View::MODE_FAST,View::MODE_SLOW][$i]<br>
     *                                  **View::MODE_AUTO** (default mode)<br>
     *                                  **View::MODE_DEBUG** errors will be more verbose, and it will compile code
     *                                  every time<br>
     *                                  **View::MODE_FAST** it will not check if the compiled file exists<br>
     *                                  **View::MODE_SLOW** it will compile the code everytime<br>
     * @param int         $commentMode  =[0,1,2][$i] <br>
     *                                  **0** comments are generated as php code.<br>
     *                                  **1** comments are generated as html code<br>
     *                                  **2** comments are ignored (no code is generated)<br>
     */
    public function __construct($templatePath = null, $compiledPath = null, $mode = 0, $commentMode = 0)
    {
        if ($templatePath === null) {
            $templatePath = \getcwd() . '/views';
        }
        if ($compiledPath === null) {
            $compiledPath = \getcwd() . '/compiles';
        }
        $this->templatePath = (is_array($templatePath)) ? $templatePath : [$templatePath];
        $this->compiledPath = $compiledPath;
        $this->setMode($mode);
        $this->setCommentMode($commentMode);
        self::$instance = $this;
        $this->authCallBack = function(
            $action = null,
            /** @noinspection PhpUnusedParameterInspection */
            $subject = null
        ) {
            return \in_array($action, $this->currentPermission, true);
        };
        $this->authAnyCallBack = function($array = []) {
            foreach ($array as $permission) {
                if (\in_array($permission, $this->currentPermission ?? [], true)) {
                    return true;
                }
            }
            return false;
        };
        $this->errorCallBack = static function(
            /** @noinspection PhpUnusedParameterInspection */
            $key = null
        ) {
            return false;
        };
        // If the "traits" has "Constructors", then we call them.
        // Requisites.
        // 1- the method must be public or protected
        // 2- it must don't have arguments
        // 3- It must have the name of the trait. i.e. trait=MyTrait, method=MyTrait()
        $traits = get_declared_traits();
        $currentTraits = (array)class_uses($this);
        foreach ($traits as $trait) {
            $r = explode('\\', $trait);
            $name = end($r);
            if (!in_array($trait, $currentTraits, true)) {
                continue;
            }
            if (is_callable([$this, $name]) && method_exists($this, $name)) {
                $this->{$name}();
            }
        }
    }

    /**
     * It gets an instance of Bladeone or will create a new one. This function is useful if you want a singleton<br>
     * **Example**
     * ```
     * $blade=View::getInstance();
     * $blade=View::getInstance("templatepath","compilepath",View::MODE_AUTO,0);
     * ```
     * @param string|array $templatePath If null then it uses (caller_folder)/views
     * @param string       $compiledPath If null then it uses (caller_folder)/compiles
     * @param int          $mode         =[View::MODE_AUTO,View::MODE_DEBUG,View::MODE_FAST,View::MODE_SLOW][$i]<br>
     *                                   **View::MODE_AUTO** (default mode)<br>
     *                                   **View::MODE_DEBUG** errors will be more
     *                                   verbose, and it will compile code every time<br>
     *                                   **View::MODE_FAST** it will not check if the
     *                                   compiled file exists<br>
     *                                   **View::MODE_SLOW** it will compile the code
     *                                   everytime<br>
     * @param int          $commentMode  =[0,1,2][$i] <br>
     *                                   **0** comments are generated as php code.<br>
     *                                   **1** comments are generated as html code<br>
     *                                   **2** comments are ignored (no code is
     *                                   generated)<br>
     * @return View
     */
    public static function getInstance($templatePath = null, $compiledPath = null, $mode = 0, $commentMode = 0): View
    {
        if (self::$instance === null) {
            new self($templatePath, $compiledPath, $mode, $commentMode);
        }
        return self::$instance;
    }

    /**
     * It adds a control to the stack<br>
     * **Example:**<br>
     * ```
     * $this->addControlStackChild('alert',['message'=>'hello']);
     * ```
     * @param string $name the nametag of the stack
     * @param array  $args
     * @return void
     */
    public function addControlStackChild(string $name, array $args): void
    {
        $this->controlStack[] = ['name' => $name, 'args' => $args, 'parent' => $this->controlStackParent];
        $this->controlStackParent = array_key_last($this->controlStack);
    }

    public function addControlStackSibling(string $name, array $args): void
    {
        $grandparent = $this->controlStack[$this->controlStackParent]['parent'];
        $this->controlStack[] = ['name' => $name, 'args' => $args, 'parent' => $grandparent];
    }

    /**
     * It returns the lastest control from the stack and removes it.
     * @return mixed|null
     */
    public function closeControlStack()
    {
        $this->controlStackParent = $this->controlStack[$this->controlStackParent]['parent'];
        return array_pop($this->controlStack);
    }

    /**
     * It removes the last parent and returns the new parent (the previous grandparent)<br>
     * Usually this method and closeControlStack must return the same if every child was closed correctly.
     * @return mixed|null
     */
    public function closeControlStackParent()
    {
        $grandparent = $this->controlStack[$this->controlStackParent]['parent'];
        unset($this->controlStack[$this->controlStackParent]);
        $this->controlStackParent = $grandparent;
        return $this->controlStack[$this->controlStackParent];
    }

    /**
     * It returns the last control from the stack without removing it.<br>
     * It is useful to get the previous control, it could be a parent or a sibling.
     * @return array
     */
    public function lastControlStack(): array
    {
        return @end($this->controlStack);
    }

    /**
     * It gets the parent control stack
     * @return array
     */
    public function parentControlStack(): array
    {
        return $this->controlStack[$this->controlStackParent];
    }

    /**
     * It clears the whole control stack
     * @return void
     */
    public function clearControlStack(): void
    {
        $this->controlStack = [['name' => '', 'args' => [], 'parent' => 0]];
    }

    /**
     * It adds a new method<br>
     * **Example:**<br>
     * ```
     * $this->addMethod('compile','alert',static function(?string $expression=null) { return });
     * $this->addMethod('runtime','alert',function(?array $arguments=null) { return });
     * ```
     * @param string   $type     =['compile','runtime'][$i] if you want to add a compile method or a runtime method
     * @param string   $name     the name of the method. Commonly it is in lowercase.
     * @param callable $callable the callable method
     * @return View
     */
    public function addMethod(string $type, string $name, callable $callable): View
    {
        $fullName = $type . ucfirst($name);
        $this->methods[$fullName] = $callable;
        return $this;
    }

    /**
     * It clears all the methods defined.
     * @return $this
     */
    public function clearMethods(): self
    {
        $this->methods = [];
        return $this;
    }

    /**
     * Used for @_n directive.
     *
     * @param $expression
     *
     * @return string
     */
    protected function compile_n($expression): string
    {
        return $this->phpTagEcho . "\$this->_n$expression; ?>";
    }
}