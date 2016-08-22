<?php

namespace DestinationServicesModule;

use ConstantsModule\DestinationTileAPIs;
use Monolog\Logger;

require_once(__DIR__ . '/../lib/OAuth.php');

class YelpService
{
	const API_HOST = 'api.yelp.com';
	const SEARCH_LIMIT = 20;
	const SEARCH_PATH = '/v2/search/';
	const BUSINESS_PATH = '/v2/business/';
	//
	const ZERO_RESULTS = 'zero';
	const ERROR_RESULTS = 'error';

	/** @var $log Logger */
	private $log;
	/** @var DestinationApiLogger $dbLogger */
	private $dbLogger;
	private $settings;

	public function __construct(ADestinationApiLogger $dbLogger)
	{
		\Logger::configure(__DIR__ . '/../config/destination_content.xml');
		$this->log = \Logger::getLogger('yelpDestinationContentLogger');

		$this->dbLogger = $dbLogger;
		$this->dbLogger->setServiceType(DestinationTileAPIs::YELP);
	}

	/**
	 * Queries the API by the input values from the user
	 *
	 * @param     $term        The search term to query
	 * @param     $location    The location of the business to query
	 * @param     $offset      The query offset
	 */
	public function getPlaces($term, $location, $offset = 0)
	{
		$rawResponse = $this->callApi($term, $location, $offset);
		$response = json_decode($rawResponse);
		//		$rawResponse = $this->getBusiness($businessId);
		//		$response = json_decode($rawResponse);
		$this->dbLogger->flush();

		return $response;
	}

	/**
	 * Query the Business API by business_id
	 *
	 * @param    $businessId    The ID of the business to query
	 * @return   The JSON response from the request
	 */
	public function getDetails($businessId)
	{
		$businessPath = self::BUSINESS_PATH . urlencode($businessId);
		$rawResponse = $this->request(self::API_HOST, $businessPath);
		$response = json_decode($rawResponse);

		$this->dbLogger->flush();
		
		return $response;
	}

	/**
	 * Query the Search API by a search term and location
	 *
	 * @param     $term        The search term passed to the API
	 * @param     $location    The search location passed to the API
	 * @param     $offset      The query offset
	 * @return   The JSON response from the request
	 */
	private function callApi($term, $location, $offset)
	{
		$params = array();

		$params['term'] = $term;
		$params['location'] = $location;
		$params['offset'] = $offset;
		$params['limit'] = self::SEARCH_LIMIT;
		$searchPath = self::SEARCH_PATH . "?" . http_build_query($params);

		return $this->request(self::API_HOST, $searchPath);
	}

	/**
	 * Makes a request to the Yelp API and returns the response
	 *
	 * @param    $host    The domain host of the API
	 * @param    $path    The path of the APi after the domain
	 * @return   The JSON response from the request
	 */
	private function request($host, $path)
	{
		$unsignedUrl = "https://" . $host . $path;
		$this->dbLogger->setCalledUrl($unsignedUrl);
		$token = new \OAuthToken($this->settings['token'], $this->settings['secret']);
		$consumer = new \OAuthConsumer($this->settings['consumerKey'], $this->settings['consumerSecret']);

		// Yelp uses HMAC SHA1 encoding
		$signatureMethod = new \OAuthSignatureMethod_HMAC_SHA1();
		$oauthRequest = \OAuthRequest::from_consumer_and_token(
			$consumer,
			$token,
			'GET',
			$unsignedUrl
		);

		// Sign the request
		$oauthRequest->sign_request($signatureMethod, $consumer, $token);
		// Get the signed URL
		$signed_url = $oauthRequest->to_url();

		// Send Yelp API Call
		try {
			$ch = curl_init($signed_url);
			if (FALSE === $ch) {
				throw new \Exception('YELP Service: Failed to initialize');
			}
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			$data = curl_exec($ch);
			if (FALSE === $data) {
				throw new \Exception('YELP Service: ' . curl_error($ch), curl_errno($ch));
			}
			$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			if (200 != $http_status) {
				$this->log->error('YELP Service error: ' . $data, $http_status);
				return self::ZERO_RESULTS;
			}
			curl_close($ch);
		} catch (\Exception $e) {
			$this->log->error(sprintf('YELP Service failed with error #%d: %s', $e->getCode(), $e->getMessage()));
			return self::ERROR_RESULTS;
		}

		$this->dbLogger->setResponseData($data);
		return $data;
	}

	public function setSettings($consumerKey, $consumerSecret, $token, $secret)
	{
		$this->settings = array(
			'consumerKey'    => $consumerKey,
			'consumerSecret' => $consumerSecret,
			'token'          => $token,
			'secret'         => $secret
		);
	}

}