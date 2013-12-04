<?php
/**
 * Detects various problems specific to Mocha specifications
 */
final class ArcanistMochaSpecificationLinter extends ArcanistLinter {
  const LINT_ONLY_DIRECTIVE = 0;

  public function getLinterName() {
    return 'MochaSpecs';
  }

  public function getLintSeverityMap() {
    return array(
      self::LINT_ONLY_DIRECTIVE => ArcanistLintSeverity::SEVERITY_ERROR
    );
  }

  public function getLintNameMap() {
    return array(
      self::LINT_ONLY_DIRECTIVE => '"only" directive left in Mocha specification'
    );
  }

  public function willLintPaths(array $paths) {
    return;
  }

  public function lintPath($path) {
    if ($this->getEngine()->isBinaryFile($path)) {
      return;
    }

    $lines = phutil_split_lines($this->getData($path), false);

    foreach ($lines as $lineno => $line) {
      // Check for 'it.only' or 'describe.only'
      if (preg_match('@^\s*(it|describe)\.only@', $line)) {
        $this->raiseLintAtLine(
          $lineno + 1,
          0,
          self::LINT_ONLY_DIRECTIVE,
          "This directive will cause other tests to be skipped");
      }
    }
  }
}
