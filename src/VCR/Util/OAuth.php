<?php

namespace VCR\Util;

use VCR\LibraryHooks\OAuthHook;
use VCR\LibraryHooks\SoapHook;
use VCR\Response;
use VCR\VCRFactory;

/**
 * OAuth class that replaces PHPs \OAuth to allow interception.
 */
class OAuth extends \OAuth
{
    /**
     * @var \VCR\LibraryHooks\OAuthHook OAuth library hook used to intercept OAuth requests.
     */
    protected $oauthHook;

    /**
     * @var Response $lastResponse object that stores information about the last response.  It doesn't matter
     * if the response is from a playback or fetch through the parent fetch method.
     */
    protected $lastResponse = null;

    public function __construct($consumer_key, $consumer_secret, $signature_method = OAUTH_SIG_METHOD_HMACSHA1, $auth_type = OAUTH_AUTH_TYPE_AUTHORIZATION) {
        parent::__construct($consumer_key, $consumer_secret, $signature_method = OAUTH_SIG_METHOD_HMACSHA1, $auth_type = OAUTH_AUTH_TYPE_AUTHORIZATION);
    }

    /**
     * * Performs (and may intercepts) SOAP request over HTTP.
     *
     * @see \OAuth::fetch
     *
     * Requests will be intercepted if the library hook is enabled.
     * @param string $protected_resource_url
     * @param array $extra_parameters
     * @param string $http_method
     * @param array $http_headers
     * @return mixed|void
     */
    public function fetch ($protected_resource_url, $extra_parameters, $http_method, $http_headers )
    {
        $oauthHook = $this->getLibraryHook();
        if($oauthHook->isEnabled()) {
            $result = $oauthHook->fetch($this, $protected_resource_url, $extra_parameters, $http_method, $http_headers);
        } else {
            $result = $this->doRealFetch($protected_resource_url, $extra_parameters, $http_method, $http_headers);
        }
        return $result;
    }


    /**
     * Helper function to set the $this->lastResponse object
     * @param array $lastResponseInfo
     * @param string $lastResponseHeaders
     * @param string $lastResponseBody
     */
    private function setLastResponse($lastResponseInfo, $lastResponseHeaders, $lastResponseBody) {
        //parse out raw header into an associative array of headers
        $headers = preg_split('/[\r\n]/', $lastResponseHeaders);
        $headersPair = array();
        foreach( $headers as $value ) {
            if(preg_match('/:\s/', $value)) {
                $pair = preg_split('/:\s/', $value);
                $headersPair[$pair[0]] = $pair[1];
            }
        }

        $this->lastResponse = new Response($lastResponseInfo['http_code'], $headersPair, $lastResponseBody);
        $this->lastResponse->setInfo($lastResponseInfo);
    }

    public function getLastResponseHeaders () {

        if($this->lastResponse != null) {
            return $this->lastResponse->getRawHeaders();
        }
    }

    public function getLastResponseInfo() {
        if($this->lastResponse != null) {
            return $this->lastResponse->getInfo();
        }
    }

    public function getLastResponse() {
        if($this->lastResponse != null) {
            return $this->lastResponse->getBody(true);
        }
    }

    public function getLastResponseObject() {
        return $this->lastResponse;
    }

    public function setLastResponseObject(Response $response) {
        $this->lastResponse = $response;
    }

    /**
     * Call parent Fetch function directly and store the response in $response object
     * @param $protected_resource_url
     * @param array $extra_parameters
     * @param $http_method
     * @param array $http_headers
     * @return boolean $result
     */
    public function doRealFetch($protected_resource_url, $extra_parameters, $http_method, $http_headers ) {

        $result = parent::fetch($protected_resource_url, $extra_parameters, $http_method, $http_headers);
        $this->setLastResponse(parent::getLastResponseInfo(), parent::getLastResponseHeaders(), parent::getLastResponse());
        return($result);
    }

    /**
     * Sets the OAuth library hook which is used to intercept SOAP requests.
     *
     * @param OAuthHook $hook OAuth library hook to use when intercepting OAuthHook requests.
     */
    public function setLibraryHook(OAuthHook $hook)
    {
        $this->oauthHook = $hook;
    }

    /**
     * Returns currently used OAuth library hook.
     *
     * If no library hook is set, a new one is created.
     *
     * @return OAuthHook oauth library hook.
     */
    protected function getLibraryHook()
    {
        if (empty($this->oauthHook)) {
            $this->oauthHook = VCRFactory::get('VCR\LibraryHooks\OAuthHook');
        }

        return $this->oauthHook;
    }
}
