<?php

namespace DestinationServicesModule;

use Config\Container;
use ConstantsModule\DestinationTileAPIs;

class GooglePlacesService
{

	const API_URL = "https://maps.googleapis.com/maps/api/place/";
	const SEARCH_METHOD = "textsearch";
	const DETAILS_METHOD = "details";
	const OUTPUT_FORMAT = 'json';
	//
	const ERROR_RESULTS = 'error';

	/** @var $log Logger */
	private $log;
	/** @var DestinationApiLogger $dbLogger */
	private $dbLogger;

	public function __construct()
	{
		\Logger::configure(__DIR__ . '/../Config/logger.d/destination_content.xml');
		$this->log = \Logger::getLogger('googleDestinationContentLogger');

		$this->dbLogger = new \Services\DestinationApiLogger();
		$this->dbLogger->setServiceType(DestinationTileAPIs::GOOGLE_PLACES);
	}

	public function getPlaces($term, $location)
	{
		$url = $this->buildSearchQuery($term, $location);
		$response = $this->callApi($url);
		return $response;
	}

	public function getNextPage($nextPageToken)
	{
		$url = $this->buildSearchQuery(NULL, NULL, $nextPageToken);
		$response = $this->callApi($url);
		return $response;
	}

	public function getDetail($placeId)
	{
		$url = $this->buildDetailQuery($placeId);
		$response = $this->callApi($url);
		return $response;
	}

	private function callApi($url)
	{
		$this->dbLogger->setCalledUrl($url);

		try {
			$ch = curl_init($url);
			if (FALSE === $ch) {
				throw new \Exception('Failed to initialize.');
			}
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			$rawData = curl_exec($ch);
			if (FALSE === $rawData) {
				throw new \Exception('False rawdata ' . curl_error($ch), curl_errno($ch));
			}
			$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			if (200 != $http_status) {
				throw new \Exception('HTTP status error ' . curl_error($ch), curl_errno($ch) . " data: " . $rawData . " http_status: " . $http_status);
			}

			$this->dbLogger->setResponseData($rawData);
			$data = json_decode($rawData);

			if ($data->status !== "OK") {
				$msg = isset($data->error_message) ? $data->error_message : "";
				throw new \Exception('Response status: ' . $data->status . ' error message: ' . $msg);
			}

			curl_close($ch);
		} catch (\Exception $e) {
			$this->log->error(sprintf('Google Places Service failed with error #%d: %s', $e->getCode(), $e->getMessage()));
			return self::ERROR_RESULTS;
		}

		$this->dbLogger->flush();

		return $data;
	}

	private function buildSearchQuery($term, $location, $nextPageToken = NULL)
	{
		if (!$nextPageToken) {
			$params['query'] = $term . " in " . $location;
		} else {
			$params['pagetoken'] = $nextPageToken;
		}
		$params['key'] = Container::$config['google_places_key'];

		return self::API_URL . self::SEARCH_METHOD . "/" . self::OUTPUT_FORMAT . "?" . http_build_query($params);
	}

	private function buildDetailQuery($placeId)
	{
		$params['placeid'] = $placeId;
		//$params['extensions'] = 'review_summary';
		$params['key'] = Container::$config['google_places_key'];

		return self::API_URL . self::DETAILS_METHOD . "/" . self::OUTPUT_FORMAT . "?" . http_build_query($params);
	}

}