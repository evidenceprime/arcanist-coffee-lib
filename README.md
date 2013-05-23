arcanist-coffee-lib
===================

Set of Arcanist classes for working with applications written in CoffeeScript.

## General usage

Typically, you will want to clone this repository next to your project and your
own Arcanist library, then load it in your `.arcconfig`:

```
...
"load": [
  "arcanist-coffee-lib",
  "your-library"
],
...
```

Then you can construct and configure the classes, e.g. in your lint engine:

```php
<?php
final class SampleLintEngine extends ArcanistLintEngine {
  public function buildLinters() {
    $paths = $this->getPaths();

    // Remove any paths that don't exist before we add paths to linters. We want
    // to do this for linters that operate on file contents because the
    // generated list of paths will include deleted paths when a file is removed.
    // Also remove directories, as the linters expect files
    foreach ($paths as $key => $path) {
      if (!$this->pathExists($path) || is_dir($path)) {
        unset($paths[$key]);
      }
    }

    // skip external libraries
    $paths = preg_grep('@/vendor/@', $paths, PREG_GREP_INVERT);

    $linters = array();

    // linters for specific file types
    $linters['COFFEESCRIPT'] = new ArcanistCoffeeLintLinter();
    $linters['COFFEESCRIPT']->setPaths(preg_grep('@\.coffee$@', $paths));

    $linters['JSON'] = new ArcanistJsonlintLinter();
    $linters['JSON']->setPaths(preg_grep('@\.json$@', $paths));

    return $linters;
  }
}
?>
```

## CoffeeLint linter

CoffeeLint linter is configurable by supplying a JSON file as described on
[CoffeeLint homepage](http://www.coffeelint.org/). The file can be then pointed
to in your `.arcconfig` with (paths are relative to `.arcconfig` location):

```
"lint.coffeelint.config": "coffeelint.json"
```
