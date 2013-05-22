<?php

/**
 * A wrapper for CoffeeLint linter, heavily inspired by ArcanistJSHintLinter. 
 * To use this linter, you need to install coffeelint through NPM.
 *
 * If you have NodeJS installed you should be able to install coffeelint with
 * ##npm install coffeelint -g## (don't forget the -g flag or NPM will install
 * the package locally). If your system is unusual, you can manually specify
 * the location of coffeelint and its dependencies by configuring these keys in
 * your .arcconfig:
 *
 *   lint.coffeelint.prefix
 *   lint.coffeelint.bin
 *
 * If you want to configure custom options for your project, create a JSON
 * file with these options and add the path to the file to your .arcconfig
 * by configuring this key:
 *
 *   lint.coffeelint.config
 *
 * With CoffeeLint 0.5.5+, you can run ##coffeelint --makeconfig## to dump
 * JSON with all the options available.
 * Consult CoffeeLint homepage on the details of the config file. 
 *
 * For more options see http://www.coffeelint.org/
 */
final class ArcanistCoffeeLintLinter extends ArcanistLinter {

  const COFFEELINT_ERROR = 1;
  const COFFEELINT_WARNING = 2;

  public function getLinterName() {
    return 'CoffeeLint';
  }

  public function getLintSeverityMap() {
    return array(
      self::COFFEELINT_ERROR => ArcanistLintSeverity::SEVERITY_ERROR,
      self::COFFEELINT_WARNING => ArcanistLintSeverity::SEVERITY_WARNING
    );
  }

  public function getLintNameMap() {
    return array(
      self::COFFEELINT_ERROR => "CoffeeLint Error",
      self::COFFEELINT_WARNING => "CoffeeLint Warning"
    );
  }

  public function getCoffeeLintOptions() {
    $working_copy = $this->getEngine()->getWorkingCopy();
    $config = $working_copy->getConfig('lint.coffeelint.config');
    $options = '--csv';

    if ($config !== null) {
      $config = Filesystem::resolvePath(
        $config,
        $working_copy->getProjectRoot());

      if (!Filesystem::pathExists($config)) {
        throw new ArcanistUsageException(
          "Unable to find custom options file defined by ".
          "'lint.coffeelint.config'. Make sure that the path is correct.");
      }

      $options .= ' --file '.$config;
    }

    return $options;
  }

  private function getCoffeeLintBin() {
    $working_copy = $this->getEngine()->getWorkingCopy();
    $prefix = $working_copy->getConfig('lint.coffeelint.prefix');
    $bin = $working_copy->getConfig('lint.coffeelint.bin');

    if ($bin === null) {
      $bin = "coffeelint";
    }

    if ($prefix !== null) {
      $bin = $prefix."/".$bin;

      if (!Filesystem::pathExists($bin)) {
        throw new ArcanistUsageException(
          "Unable to find CoffeeLint binary in a specified directory. Make sure ".
          "that 'lint.coffeelint.prefix' and 'lint.coffeelint.bin' keys are set ".
          "correctly. If you'd rather use a copy of CoffeeLint installed ".
          "globally, you can just remove these keys from your .arcconfig");
      }

      return $bin;
    }

    // Look for globally installed CoffeeLint
    list($err) = (phutil_is_windows()
      ? exec_manual('where %s', $bin)
      : exec_manual('which %s', $bin));

    if ($err) {
      throw new ArcanistUsageException(
        "CoffeeLint does not appear to be installed on this system. Install it ".
        "(e.g., with 'npm install coffeelint -g') or configure ".
        "'lint.coffeelint.prefix' in your .arcconfig to point to the directory ".
        "where it resides.");
    }

    return $bin;
  }

  public function willLintPaths(array $paths) {
    if (!$this->isCodeEnabled(self::COFFEELINT_ERROR) &&
        !$this->isCodeEnabled(self::COFFEELINT_WARNING)) {
      return;
    }

    $coffeelint_bin = $this->getCoffeeLintBin();
    $coffeelint_options = $this->getCoffeeLintOptions();
    $futures = array();

    foreach ($paths as $path) {
      $filepath = $this->getEngine()->getFilePathOnDisk($path);
      $futures[$path] = new ExecFuture(
        "%s %s %C",
        $coffeelint_bin,
        $filepath,
        $coffeelint_options);
    }

    foreach (Futures($futures)->limit(8) as $path => $future) {
      $this->results[$path] = $future->resolve();
    }
  }

  public function lintPath($path) {
    if (!$this->isCodeEnabled(self::COFFEELINT_ERROR) &&
        !$this->isCodeEnabled(self::COFFEELINT_WARNING)) {
      return;
    }

    list($rc, $stdout, $stderr) = $this->results[$path];

    if ($rc !== 0) {
      // CoffeeLint exited with an error
      throw new ArcanistUsageException(
        "CoffeeLint exited with an error.\n".
        "stdout:\n\n{$stdout}".
        "stderr:\n\n{$stderr}");
    }

    $errors = explode("\n", $stdout);

    foreach ($errors as $err) {
      if (!strlen($err)) {
        continue;
      }

      $fields = explode(',', $err);
      $this->raiseLintAtLine(
        $fields[1],
        null,
        $fields[2] === 'error' ? self::COFFEELINT_ERROR : self::COFFEELINT_WARNING,
        $fields[3]);
    }
  }
}
