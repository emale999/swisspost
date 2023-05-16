<?php

class swisspost {
	
  private $client_id	      = "< Secret-ID / Client Identifier Production >"; // Secret-ID / Client Identifier Production
	private $client_secret    = "< Token-ID / Client Secret Production >"; // Token-ID / Client Secret Production
	private $frankierlizenz   = "< Frankierlizenz >";
	public  $standardabsender = array("name1" => "ACME", "street" => "Hauptstrasse 12", "zip" => "4057", "city" => "Basel", "country" => "CH");
	
	// Runtime Variablen
	private $token			= null;
	private $tokenScope		= null;
	
	public function __construct() { }
	
	private function _api($mode, $url, $data=false, $jsonOutput=false, $jsonInput=true) {
		$header = array();
		if ($jsonInput) 			$header[] = 'Content-Type: application/json'; else $header[] = 'Content-Type: application/x-www-form-urlencoded';
		if ($this->token != null)	$header[] = 'Authorization: Bearer '.$this->token;
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		if ($mode == "DELETE")   curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
		elseif ($mode == "PUT")  curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
		elseif ($mode == "POST") curl_setopt($curl, CURLOPT_POST, true);
		if ($data !== false)
			curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonInput ? json_encode($data) : http_build_query($data));
		$result = curl_exec($curl);
		$headers = curl_getinfo($curl);
		if ($headers['http_code'] == 403) throw new Exception("$url: Authorization Error (Access) - No valid access token");
// 		print_r($headers);
// 		print_r($result);
		curl_close($curl);
		return $jsonOutput ? $result : json_decode($result, true);
		}
	
	public function get($url, $data = false, $jsonOutput = false) {
		return $this->_api("GET", $url, $data, $jsonOutput);
		}

	public function post($url, $data = false, $jsonOutput = false) {
		return $this->_api("POST", $url, $data, $jsonOutput);
		}

	public function put($url, $data = false, $jsonOutput = false) {
		return $this->_api("PUT", $url, $data, $jsonOutput);
		}

	public function del($url, $data = false, $jsonOutput = false) {
		return $this->_api("DELETE", $url, $data, $jsonOutput);
		}
	
	private function getAccessToken($scope) {
		$this->token = $this->tokenScope = null;
		$data = array("grant_type" 		=> "client_credentials",
					  "client_id"		=> $this->client_id,
					  "client_secret"	=> $this->client_secret,
					  "scope"			=> $scope
					  );
		$response = $this->_api("POST", "https://wedec.post.ch/WEDECOAuth/token", $data, false, false);
		if (isset($response['access_token'])) {
			//echo($response['access_token']);
			$this->token = $response['access_token'];
			$this->tokenScope = $scope;
			return true;
		} elseif (isset($response['error'])) {
			//print_r($response);
			throw new Exception("Fehler bei Tokenabruf $scope: ".$response['error']." ".$response['error_description']);
			}
		}
		
	
	public function resetAccessToken() {
		$this->token = null;
		$this->tokenScope = null;
		}
	
	// https://developer.post.ch/en/digital-commerce-api, Absatz 5 
	// https://wedec.post.ch/doc/swagger/index.html?url=https://wedec.post.ch/doc/api/address/v1/swagger.yaml#/Address/validate
	// $adresse = array(array("addressee" => array("title" => "", "firstName" => "", "lastName" => ""), "zip" => array("zip" => ""), 
	//						  "logisticLocation" => array("house" => array("street" => "", "houseNumber" => "")))));
	public function validateAddress($adresse) {
		if ($this->token == null || $this->tokenScope != 'WEDEC_VALIDATE_ADDRESS') $this->getAccessToken('WEDEC_VALIDATE_ADDRESS');
		$response = $this->post("https://wedec.post.ch/api/address/v1/addresses/validation", $adresse);
		return $response['quality'];
		}
		
	// https://wedec.post.ch/doc/swagger/index.html?url=https://wedec.post.ch/doc/api/barcode/v1/swagger.yaml
	// https://developer.post.ch/en/digital-commerce-api, Absatz 8
	// $empfaenger = array("firstName" => "", "name1" => "", "street" => "", "zip" => "", "city" => "", "country" => "")
	// $absender = array("name1" => "", "street" => "", "zip" => "", "city" => "", "country" => "")
	// $shippingAttr = array("ECO", "PRI", "SEM", ...);
	// $saveTo = "/var/html/www/test/labels/label.pdf" oder "/var/html/www/test/labels/" <- itemcode.pdf
	// $gewicht = <Gewicht in kg>
	// $testmode = true <- PDF mit SPECIMEN-Aufdruck
	public function getLabel($empfaenger, $absender=null, $shippingAttr=array(), $saveTo=null, $gewicht=null, $testmode=false) { 
		 if ($this->token == null || $this->tokenScope != 'WEDEC_BARCODE_READ') $this->getAccessToken('WEDEC_BARCODE_READ');
 		if ($absender == null) $absender = $this->standardabsender;
 		$attributes = array();
 		if ($gewicht != null) $attributes["weight"] = $gewicht*1000; // Gewicht in g
 		if (count($shippingAttr) > 0) $attributes["przl"] = $shippingAttr; else $attributes["przl"] = array("ECO");
 		$labelDefinition = array("labelLayout" => "A6", "printAddresses" => "RECIPIENT_AND_CUSTOMER", "imageFileType" => "PDF", "imageResolution" => "300");
 		if ($testmode) $labelDefinition['printPreview'] = "true";
		$data = array("language" => "DE", "frankingLicense" => $this->frankierlizenz, "customer" => $absender, 
					  "labelDefinition" => $labelDefinition, "item" => array("recipient" => $empfaenger, "attributes" => $attributes)
					  );
		die(json_encode($data));
		$response = $this->post("https://wedec.post.ch/api/barcode/v1/generateAddressLabel", $data);
		if (isset($response[0]) && isset($response[0]['error']))
			throw new Exception("Fehler bei getLabel-Request: ".$response[0]['error']);
		else {
			if ($saveTo != null) {
				if (substr($saveTo, -1) == "/") $saveTo .= $response['item']['identCode'].".pdf";
				file_put_contents($saveTo, base64_decode($response['item']['label']));
				}
			return array("identCode" => $response['item']['identCode'], "label" => $response['item']['label']);
			}
		}
	
	}

?>
