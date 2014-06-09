<?php

namespace VCR\CodeTransform;

class OAuthCodeTransform extends AbstractCodeTransform
{
    const NAME = 'vcr_oauth';

    private static $replacements = array(
        'new \VCR\Util\OAuth(',
        'extends \VCR\Util\OAuth',
    );

    private static $patterns = array(
        '@new\s+\\\?OAuth\W*\(@i',
        '@extends\s+\\\?OAuth\s+@i',
    );

  /**
   * @inheritdoc
   */
  protected function transformCode($code)
    {
        return preg_replace(self::$patterns, self::$replacements, $code);
    }
}