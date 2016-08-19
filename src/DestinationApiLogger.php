<?php

namespace DestinationServicesModule;

abstract class DestinationApiLogger
{

	private $data = array(
		'fsid'          => NULL,
		'service_type'  => NULL,
		'called_url'    => NULL,
		'response_data' => NULL
	);

	public function __construct($fsid)
	{
		$this->data['fsid'] = $fsid;
	}

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

	/**
	 * need to be implemented in frontend and backend separately
	 *
	 * @return mixed
	 */
	abstract public function flush();
}