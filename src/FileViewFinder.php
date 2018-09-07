<?php
declare(strict_types=1);

namespace TwigBridge;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\ViewFinderInterface;
use InvalidArgumentException;

/**
 * Class FileViewFinder
 *
 * Clone of laravel FileViewFinder, but allows full names with extension and namespaces. e.g.
 * view('directory/flup/someJavascript.js')
 * will finally resolve in possible names: someJavascript.js,someJavascript/js.php,someJavascript/js.twig ...
 * 
 * last laravel change: https://github.com/laravel/framework/commit/ef52aa4a5193ac5556617d844badd71a73a0e7f7#diff-8fb84e5d9dba55a200faaf50b796941e
 *
 * @package App\Providers
 */
class FileViewFinder implements ViewFinderInterface
{
    /**
     * Hint path delimiter value for twig
     *
     * @var string
     */
    const HINT_PATH_TWIG_DELIMITER = '@';

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The array of active view paths.
     *
     * @var array
     */
    protected $paths;

    /**
     * The array of views that have been located.
     *
     * @var array
     */
    protected $views = [];

    /**
     * The namespace to file path hints.
     *
     * @var array
     */
    protected $hints = [];

    /**
     * Register a view extension with the finder.
     *
     * @var array
     */
    protected $extensions = ['blade.php', 'php', 'css'];

    /**
     * Create a new file view loader instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem $files
     * @param  array                             $paths
     * @param  array                             $extensions
     *
     * @return void
     */
    public function __construct(Filesystem $files, array $paths, array $extensions = null)
    {
        $this->files = $files;
        $this->paths = $paths;

        if (isset($extensions)) {
            $this->extensions = $extensions;
        }
    }

    /**
     * Register an extension with the view finder.
     *
     * @param  string $extension
     *
     * @return void
     */
    public function addExtension($extension)
    {
        if (($index = array_search($extension, $this->extensions)) !== false) {
            unset($this->extensions[$index]);
        }

        array_unshift($this->extensions, $extension);
    }

    /**
     * Add a location to the finder.
     *
     * @param  string $location
     *
     * @return void
     */
    public function addLocation($location)
    {
        $this->paths[] = $location;
    }

    /**
     * Add a namespace hint to the finder.
     *
     * @param  string       $namespace
     * @param  string|array $hints
     *
     * @return void
     */
    public function addNamespace($namespace, $hints)
    {
        $hints = (array)$hints;

        if (isset($this->hints[$namespace])) {
            $hints = array_merge($this->hints[$namespace], $hints);
        }

        $this->hints[$namespace] = $hints;
    }

    /**
     * Get the fully qualified location of the view.
     *
     * @param  string $name
     *
     * @return string
     */
    public function find($name)
    {
        if (isset($this->views[$name])) {
            return $this->views[$name];
        }

        if ($this->hasHintInformation($name = trim($name))) {
            // $this->views[$name] = $this->findNamespacedView($name);
            return $this->views[$name] = $this->findNamespacedView($name);
        }

        return $this->views[$name] = $this->findInPaths($name, $this->paths);
    }

    /**
     * Find the given view in the list of paths.
     *
     * @param  string $name
     * @param  array  $paths
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function findInPaths($name, $paths)
    {


        if ($name[0] === '@') {
            $split = preg_split("~\/~", $name, 2);
            $paths[] = $paths[$split[0]];
            $name = $split[1];
        }

        foreach ((array)$paths as $path) {
            foreach ($this->getPossibleViewFiles($name) as $file) {
                if ($this->files->exists($viewPath = $path . '/' . $file)) {

                    return $viewPath;
                }
            }
        }

        throw new InvalidArgumentException("View [{$name}] not found.");
    }

    /**
     * Get the path to a template with a named path.
     *
     * @param  string $name
     *
     * @return string
     */
    protected function findNamespacedView($name)
    {
        list($namespace, $view) = $this->parseNamespaceSegments($name);

        return $this->findInPaths($view, $this->hints[$namespace]);
    }

    /**
     * Flush the cache of located views.
     *
     * @return void
     */
    public function flush()
    {
        $this->views = [];
    }

    /**
     * Get registered extensions.
     *
     * @return array
     */
    public function getExtensions()
    {
        return $this->extensions;
    }

    /**
     * Get the filesystem instance.
     *
     * @return \Illuminate\Filesystem\Filesystem
     */
    public function getFilesystem()
    {
        return $this->files;
    }

    /**
     * Get the namespace to file path hints.
     *
     * @return array
     */
    public function getHints()
    {
        return $this->hints;
    }

    /**
     * Get the active view paths.
     *
     * @return array
     */
    public function getPaths()
    {
        return $this->paths;
    }

    /**
     * Get an array of possible view files.
     *
     * @param  string $name
     *
     * @return array
     */
    protected function getPossibleViewFiles($name)
    {
        // namespaces will not be removed
        $possibleFile = [preg_replace('/\.(?=.*\.)/', '/', $name)];
        $possibleFile = array_merge($possibleFile, array_map(function ($extension) use ($name) {
            return str_replace('.', '/', $name) . '.' . $extension;
        }, $this->extensions));

        return $possibleFile;
    }

    /**
     * Returns whether or not the view name has any hint information.
     *
     * @param  string $name
     *
     * @return bool
     */
    public function hasHintInformation($name)
    {
        return (strpos($name, static::HINT_PATH_DELIMITER) > 0 || strpos($name, static::HINT_PATH_TWIG_DELIMITER) === 0);
    }

    /**
     * Get the segments of a template with a named path.
     *
     * @param  string $name
     *
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    protected function parseNamespaceSegments($name)
    {
        if ($name[0] === static::HINT_PATH_TWIG_DELIMITER) {
            // twig
            $segments = preg_split("~\/~", $name, 2);

            if (count($segments) < 2) {
                throw new InvalidArgumentException("View [{$name}] has an invalid name.");
            }
        } else {
            // blade
            $segments = explode(static::HINT_PATH_DELIMITER, $name);

            if (count($segments) !== 2) {
                throw new InvalidArgumentException("View [{$name}] has an invalid name.");
            }
        }

        if (!isset($this->hints[$segments[0]])) {
            throw new InvalidArgumentException("No hint path defined for [{$segments[0]}].");
        }

        return $segments;
    }

    /**
     * Prepend a location to the finder.
     *
     * @param  string $location
     *
     * @return void
     */
    public function prependLocation($location)
    {
        array_unshift($this->paths, $location);
    }

    /**
     * Prepend a namespace hint to the finder.
     *
     * @param  string       $namespace
     * @param  string|array $hints
     *
     * @return void
     */
    public function prependNamespace($namespace, $hints)
    {
        $hints = (array)$hints;

        if (isset($this->hints[$namespace])) {
            $hints = array_merge($hints, $this->hints[$namespace]);
        }

        $this->hints[$namespace] = $hints;
    }

    /**
     * Replace the namespace hints for the given namespace.
     *
     * @param  string       $namespace
     * @param  string|array $hints
     *
     * @return void
     */
    public function replaceNamespace($namespace, $hints)
    {
        $this->hints[$namespace] = (array)$hints;
    }
}
