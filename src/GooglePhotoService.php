<?php

namespace DestinationServicesModule;

use ConstantsModule\DestinationTileAPIs;

class GooglePhotoService
{

	const API_URL = "https://maps.googleapis.com/maps/api/place/";
	const METHOD = "photo";
	//
	const ERROR_RESULTS = 'error';

	/** @var $log Logger */
	private $log;
	/** @var DestinationApiLogger $dbLogger */
	private $dbLogger;
	private $settings;

	public function __construct(ADestinationApiLogger $dbLogger)
	{
		\Logger::configure(__DIR__ . '/../config/destination_content.xml');
		$this->log = \Logger::getLogger('googlePhotosLogger');

		$this->dbLogger = $dbLogger;
		$this->dbLogger->setServiceType(DestinationTileAPIs::GOOGLE_PHOTOS);
	}

	public function getFirstImage($place, $maxHeight = NULL, $maxWidth = NULL)
	{
		if (isset($place->photos)) {
			$photoData = array_shift($place->photos);
			return $this->getImage($photoData->photo_reference, $maxHeight, $maxWidth);
		}

		return "//www.placehold.it/200x200?text=no+image";
	}

	public function getImage($photoReference, $maxHeight = NULL, $maxWidth = NULL)
	{
		if (!$photoReference) {
			$this->log->error("Google Places service error: missing 'photoreference'");
		}

		$imageUrl = $this->buildQuery($photoReference, $maxHeight, $maxWidth);

		// this is a little hack
		// image url is good enough, but contains html with 302 (Moved Permanently) redirect
		// which results into 3 redirects (4 requests) for 1 image!!
		// so I parse out actual image url from raw html
		$rawHtml = $this->callApi($imageUrl);

		$finalImageUrl = $this->parseImageUrlOut($rawHtml);

		if (!$finalImageUrl) {
			return $imageUrl;
		}
		return $finalImageUrl;
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
			$rawHtml = curl_exec($ch);
			if (FALSE === $rawHtml) {
				throw new \Exception('False rawdata ' . curl_error($ch), curl_errno($ch));
			}
			$this->dbLogger->setResponseData($rawHtml);

			curl_close($ch);
		} catch (\Exception $e) {
			$this->log->error(sprintf('Google Places Photo Service failed with error #%d: %s', $e->getCode(), $e->getMessage()));
			return self::ERROR_RESULTS;
		}

		$this->dbLogger->flush();

		return $rawHtml;
	}

	private function buildQuery($photoReference, $maxHeight, $maxWidth)
	{
		if ($maxHeight) {
			$params['maxheight'] = $maxHeight;
		} elseif ($maxWidth) {
			$params['maxwidth'] = $maxWidth;
		}
		$params['photoreference'] = $photoReference;
		$params['key'] = $this->settings['googlePlacesKey'];
		$url = self::API_URL . self::METHOD . "?" . http_build_query($params);

		return $url;
	}

	private function parseImageUrlOut($rawHtml)
	{
		preg_match("#href=['|\"](.*)['|\"]#i", $rawHtml, $matches);
		return isset($matches[1]) ? $matches[1] : NULL;
	}

	public function setSettings($googlePlacesKey)
	{
		$this->settings = array(
			'googlePlacesKey' => $googlePlacesKey,
		);
	}
}