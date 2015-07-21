<?php

/**
 * A wrapper for jsonlint linter, heavily inspired by ArcanistJSHintLinter.
 * To use this linter, you need to install jsonlint through NPM.
 *
 * The node.js implementation of linter was chosen over PHP or Python one
 * (although available in Debian repos), because other tools from this
 * library use node.js tools, so it it probably more convenient for the
 * user to configure her environment just once.
 *
 * If you have NodeJS installed you should be able to install jsonlint with
 * ##npm install jsonlint -g## (don't forget the -g flag or NPM will install
 * the package locally). If your system is unusual, you can manually specify
 * the location of jsonlint and its dependencies by configuring these keys in
 * your .arcconfig:
 *
 *   lint.jsonlint.prefix
 *   lint.jsonlint.bin
 *
 * For more options see http://zaach.github.io/jsonlint/ and
 * http://jsonlint.com/
 */
final class ArcanistJsonlintLinter extends ArcanistLinter {
  const JSONLINT_ERROR = 1;

  public function getLinterName() {
    return 'Jsonlint';
  }

  public function getLintSeverityMap() {
    return array(
      self::JSONLINT_ERROR => ArcanistLintSeverity::SEVERITY_ERROR
    );
  }

  public function getLintNameMap() {
    return array(
      self::JSONLINT_ERROR => "Jsonlint Error",
    );
  }

  public $results = array();

  private function getJsonlintBin() {
    $working_copy = $this->getEngine()->getWorkingCopy();
    $prefix = $working_copy->getProjectConfig('lint.jsonlint.prefix');
    $bin = $working_copy->getProjectConfig('lint.jsonlint.bin');

    if ($bin === null) {
      $bin = "jsonlint";
    }

    if ($prefix !== null) {
      $bin = $prefix."/".$bin;

      if (!Filesystem::pathExists($bin)) {
        throw new ArcanistUsageException(
          "Unable to find Jsonlint binary in a specified directory. Make sure ".
          "that 'lint.jsonlint.prefix' and 'lint.jsonlint.bin' keys are set ".
          "correctly. If you'd rather use a copy of jsonlint installed ".
          "globally, you can just remove these keys from your .arcconfig");
      }

      return $bin;
    }

    // Look for globally installed jsonlint
    list($err) = (phutil_is_windows()
      ? exec_manual('where %s', $bin)
      : exec_manual('which %s', $bin));

    if ($err) {
      throw new ArcanistUsageException(
        "Jsonlint does not appear to be installed on this system. Install it ".
        "(e.g., with 'npm install jsonlint -g') or configure ".
        "'lint.jsonlint.prefix' in your .arcconfig to point to the directory ".
        "where it resides.");
    }

    return $bin;
  }

  public function willLintPaths(array $paths) {
    if (!$this->isCodeEnabled(self::JSONLINT_ERROR)) {
      return;
    }

    $jsonlint_bin = $this->getJsonlintBin();
    $jsonlint_options = "-q -c";
    $futures = array();

    foreach ($paths as $path) {
      $filepath = $this->getEngine()->getFilePathOnDisk($path);
      $futures[$path] = new ExecFuture(
        "%s %C %s",
        $jsonlint_bin,
        $jsonlint_options,
        $filepath);
    }
    $futures = id(new FutureIterator($futures))
      ->limit(8);
    foreach ($futures as $path => $future) {
      $this->results[$path] = $future->resolve();
    }
  }

  private function extractNumber($string) {
    preg_match('@[0-9]+$@', $string, $matches);
    return $matches[0];
  }

  public function lintPath($path) {
    if (!$this->isCodeEnabled(self::JSONLINT_ERROR)) {
      return;
    }

    list($rc, $stdout, $stderr) = $this->results[$path];

    if ($rc === 0) {
      // File linted without errors
      return;
    }

    $errors = explode("\n", $stderr);

    foreach ($errors as $err) {
      if (!strlen($err)) {
        continue;
      }

      $tmp = explode(':', $err, 2);
      $info = $tmp[1];

      $fields = explode(',', $info, 3);
      $this->raiseLintAtLine(
        $this->extractNumber($fields[0]),
        $this->extractNumber($fields[1]),
        self::JSONLINT_ERROR,
        $fields[2]);
    }
  }
}
