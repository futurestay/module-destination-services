<?php

namespace Services;

// require model file
require_once(__DIR__ . "/../public_html/manage/Models/tilesModel.php");

class DestinationApiLogger
{

	private $data = array(
		'fsid'          => NULL,
		'service_type'  => NULL,
		'called_url'    => NULL,
		'response_data' => NULL
	);

	public function __construct()
	{
		$this->data['fsid'] = $_SESSION['FSID'];
	}

	//	public function setFsid($fsid)
	//	{
	//		$this->data['fsid'] = $fsid;
	//	}

	public function setServiceType($service)
	{
		$this->data['service_type'] = $service;
	}

	public function setCalledUrl($url)
	{
		$this->data['called_url'] = $url;
	}

	public function setResponseData($data)
	{
		$this->data['response_data'] = str_replace(array("\r\n", "\n", "\r"), '', $data);
	}

	public function flush()
	{
		\Services\DbHandler::connectToMainDB();
		$success = saveDestinationLogEntry($this->data);
	}

}