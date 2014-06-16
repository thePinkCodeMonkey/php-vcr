<?php

namespace VCR\LibraryHooks;

use VCR\Util\Assertion;
use VCR\Util\OAuth;
use VCR\VCRException;
use VCR\Request;
use VCR\CodeTransform\AbstractCodeTransform;
use VCR\Util\StreamProcessor;

/**
 * Library hook for curl functions.
 */
class OAuthHook implements LibraryHook
{
    /**
     * @var string
     */
    private static $requestCallback;

    /**
     * callback for recording an oauth response
     * @var string
     */
    private static $recordCallback;

    /**
     * @var string
     */
    private $status = self::DISABLED;

    /**
     * @var AbstractCodeTransform
     */
    private $codeTransformer;

    /**
     * @var \VCR\Util\StreamProcessor
     */
    private $processor;

    /**
     * Creates a OAuth hook instance.
     *
     * @param AbstractCodeTransform  $codeTransformer
     * @param StreamProcessor $processor
     *
     * @throws \BadMethodCallException in case the Soap extension is not installed.
     */
    public function __construct(AbstractCodeTransform $codeTransformer, StreamProcessor $processor)
    {
        if (!class_exists('\OAuth')) {
            throw new \BadMethodCallException('For OAuth support you need to install the OAuth extension.');
        }

        $this->processor = $processor;
        $this->codeTransformer = $codeTransformer;
    }

    /**
     * @inheritDoc
     */
    public function enable(\Closure $requestCallback)
    {
        Assertion::isCallable($requestCallback, 'No valid callback for handling requests defined.');
        self::$requestCallback = $requestCallback;

        if ($this->status == self::ENABLED) {
            return;
        }

        $this->codeTransformer->register();
        $this->processor->appendCodeTransformer($this->codeTransformer);
        $this->processor->intercept();

        $this->status = self::ENABLED;
    }

    /**
     * sets the callback to record a pair of request/response
     * @param callable $recordCallback
     */
    public function setRecordCallback(\Closure $recordCallback) {
        Assertion::isCallable($recordCallback, 'No valid callback for handling requests defined.');
        self::$recordCallback = $recordCallback;
        return;
    }

    /**
     * The method called when an oAuth fetch function is intercepted.  Responsible for recording / playback of the
     * request and response
     *
     * @param OAuth $oauthClient - the oAuth client used to make the request if a recording cannot be found.
     * @param string $protected_resource_url
     * @param array $extra_parameters
     * @param string $http_method
     * @param array $http_headers
     */
    public function fetch($oauthClient, $protected_resource_url, $extra_parameters, $http_method, $http_headers) {

        if ($this->status === self::DISABLED) {
            throw new VCRException('OAuth Hook must be enabled.', VCRException::LIBRARY_HOOK_DISABLED);
        }

        //package the request into a Request object
        $vcrRequest = new Request($http_method, $protected_resource_url, $http_headers);

        //not sure where to store the extra_params yet
        //$vcrRequest->setBody($extra_parameters);

        $requestCallback = self::$requestCallback;
        $response = $requestCallback($vcrRequest);

        if($response == null) {
            //$oauthClient->setLastResponseObject($response);
            $result = $oauthClient->doRealFetch($protected_resource_url, $extra_parameters, $http_method, $http_headers);
            if($result == true) {
                $response = $oauthClient->getLastResponseObject();
                $recordRequestCallback = self::$recordCallback;
                $recordRequestCallback($vcrRequest, $response);
            }
        } else {
            $oauthClient->setLastResponseObject($response);
            $result = true;
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function disable()
    {
        if (!$this->isEnabled()) {
            return;
        }

        self::$requestCallback = null;

        $this->status = self::DISABLED;
    }

    /**
     * @inheritDoc
     */
    public function isEnabled()
    {
        return $this->status == self::ENABLED;
    }

    /**
     * Cleanup.
     *
     * @return  void
     */
    public function __destruct()
    {
        self::$requestCallback = null;
    }
}
