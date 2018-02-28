<?php
/**
 * Detects various problems specific to Mocha specifications
 */
final class ArcanistMochaSpecificationLinter extends ArcanistLinter {
  const LINT_ONLY_DIRECTIVE = 0;
  const LINT_NO_MATCHER = 1;
  const LINT_NO_MATCHER_WARNING = 2;

  public function getLinterName() {
    return 'MochaSpecs';
  }

  public function getLintSeverityMap() {
    return array(
      self::LINT_ONLY_DIRECTIVE => ArcanistLintSeverity::SEVERITY_ERROR,
      self::LINT_NO_MATCHER_WARNING => ArcanistLintSeverity::SEVERITY_WARNING
    );
  }

  public function getLintNameMap() {
    return array(
      self::LINT_ONLY_DIRECTIVE => '"only" directive left in Mocha specification',
      self::LINT_NO_MATCHER => 'invalid Chai assertion: matcher (e.g. `equal`) needs to come last',
      self::LINT_NO_MATCHER_WARNING => 'Possible invalid Chai assertion: matcher (e.g. `has`) needs to come last'
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

    // 'at' is also in this list, but it is Backbone.Colllection method as well
    $chaiLanguageChain = join('|', array('be', 'been', 'is', 'and', 'have',
      'with', 'that', 'of', 'same', 'not'));

    $chaiLanguageWarningChain = 'has';

    foreach ($lines as $lineno => $line) {
      // Check for 'it.only' or 'describe.only'
      if (preg_match('@^\s*(it|describe)\.only@', $line)) {
        $this->raiseLintAtLine(
          $lineno + 1,
          0,
          self::LINT_ONLY_DIRECTIVE,
          "This directive will cause other tests to be skipped");
      } else if (preg_match("@\.($chaiLanguageChain)( |\()@", $line)) {
        $this->raiseLintAtLine(
          $lineno + 1,
          0,
          self::LINT_NO_MATCHER,
          "This assertion is incomplete and will succeed for any input");
      } else if (preg_match("@\.($chaiLanguageWarningChain)( |\()@", $line)) {
        $this->raiseLintAtLine(
          $lineno + 1,
          0,
          self::LINT_NO_MATCHER_WARNING,
          "This assertion is incomplete and it might succeed for any input, but it might be throwed when using Immutable.has in test");
      }
    }
  }
}
