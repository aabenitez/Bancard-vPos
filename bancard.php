<?php
class Bancard {
	
	private $publicKey;
	private $privateKey;
	private $token;
	private $userConfig;
	private $process_id;
	private $base_url;
	private $return_url;
	private $cancel_url;
	private $cards_url;
	private static $config;
	private static $storeName;
	private static $enviroment;
	private static $baseAPIUrl;
	private static $basePaymentUrl;
	
    function __construct()
    {
		
	/*
		CLAVES DE API
		Estas claves se obtienen del Portal de Comercios de Bancard
	*/
		self::$basePaymentUrl = "https://vpos.infonet.com.py/payment/single_buy?process_id=";

		$config = self::loadConfig();		

		self::$enviroment = $config->enviroment;
		self::$storeName = $config->store->name;

		$this->publicKey = $config->credentials->public;
		$this->privateKey = $config->credentials->private;
		

		if(@$config->enviroment){
			self::$baseAPIUrl = "https://vpos.infonet.com.py:8888";		// DEVELOPMENT			
		}else{
			self::$baseAPIUrl = "https://vpos.infonet.com.py";			// PRODUCTION
		}
		
	/*
		URLs de retorno de la Aplicación
		Son los controladores a los que se accede una vez que se finaliza o cancela la transaccion.
		
		NO DEBEN CONFUNDIRSE CON LA URL DE CONFIRMACIÓN!!!
	*/
		
		$this->description = $config->store->name;		

		$this->base_url = $config->store->url;	
		$this->return_url = $config->store->url."/".$config->callbacks->return;	
		$this->cancel_url = $config->store->url."/".$config->callbacks->cancel;	
		$this->cards_url = $config->store->url."/".$config->callbacks->newCard;	

		$this->process_id = null;	
		
    }

    public function __get($name)
    {
    	if($name == "paymentUrl"){
    		if($this->process_id) return self::$basePaymentUrl.$this->process_id;
    	}elseif($name == "referer"){
    		return $this->base_url;
    	}
    }

    public static function storeName()
    {
		$config = self::loadConfig();	
    	return (string)$config->store->name;
    }

    public static function enviroment()
    {
		$config = self::loadConfig();	
    	return (string)$config->enviroment;
    }

    private static function loadConfig()
    {
    	$file = fopen(__DIR__."/config.xml","r");
    	$xml = fread($file, filesize(__DIR__."/config.xml"));
    	fclose($file);
    	$config = new SimpleXMLElement($xml);
    	return $config;
    }


    private function send($url,$request,$method="GET")
    {

    	$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => self::$baseAPIUrl.$url,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_POSTFIELDS => json_encode($request)
		));
		
		$resp = curl_exec($curl);
		$this->handleCurlError(curl_error($curl));

		curl_close($curl);

		$resp = json_decode($resp);
		
		return $resp;
				
    }

    private function handleCurlError($curlError)
    {
    	// var_dump($curlError);
    	if($curlError != '') throw new Exception("Bancard API Connection Error: {$curlError}", 1);    	
    }
    private function handleApiError($data)
    {
    	error_log("Bancard Error");
    	error_log(print_r($data,true));
    	return true;
    }

    private function get($url,$request){
    	return $this->send($url,$request);
    }

    private function post($url,$request){
    	return $this->send($url,$request,"POST");
    }

    private function delete($url,$request){
    	return $this->send($url,$request,"DELETE");
    }

	public function getCards($user_id){

		$this->token = md5($this->privateKey . $user_id . "request_user_cards");
		
		$request = array(
			"public_key" => "{$this->publicKey}",
			"operation" => array(
				"token" => $this->token
			)
		);

		$endpoint = "/vpos/api/0.3/users/{$user_id}/cards";

		$resp = $this->post($endpoint,$request);
		
		if(@$resp->status == "success"){
			return $resp;
		}else{
			$this->handleApiError($resp);
			return false;
		}		
	}

	public function newCard($user_id,$user_cell_phone,$user_mail){

		$card_id = $this->maxCardID($user_id) + 1;

		$this->token = md5($this->privateKey . $card_id . $user_id . "request_new_card");
		
		$request = array(
			"public_key" => "{$this->publicKey}",
			"operation" => array(
				"token" => $this->token,
				"card_id" => $card_id,
				"user_id" => $user_id,
				"user_cell_phone" => $user_cell_phone,
				"user_mail" => $user_mail,
				"return_url" => $this -> base_url."?user_id={$user_id}&card_id={$card_id}",
			)
		);

		$queryString = array(
				"user_id" => $user_id,
				"user_cell_phone" => $user_cell_phone,
				"user_mail" => $user_mail
		);

		$endpoint = "/vpos/api/0.3/cards/new";

		$resp = $this->post($endpoint,$request);
		

		if(@$resp->status == "success"){
			return $resp;
		}else{
			$this->handleApiError($resp);
			return false;
		}		
	}

	public function removeCard($card_id,$user_id){

		$card = $this->getCard($card_id,$user_id);

		if(!$card){
			error_log("Lib Error @ Bancard::removeCard => Card Not Found");
			return false;
		}

		$this->token = md5($this->privateKey . "delete_card" . $user_id . $card->alias_token);
		
		$request = array(
			"public_key" => "{$this->publicKey}",
			"operation" => array(
				"token" => $this->token,
				"alias_token" => $card->alias_token,
			)
		);

		$endpoint = "/vpos/api/0.3/users/{$user_id}/cards";

		$resp = $this->delete($endpoint,$request);
		
		// var_dump($resp);

		if(@$resp->status == "success"){
			return $resp;
		}else{
			$this->handleApiError($resp);
			return false;
		}		
	}

	private function getCard($card_id='',$user_id='')
	{
		$cards = $this->getCards($user_id);

		foreach ($cards->cards as $cardData) {
			if($cardData->card_id == $card_id){
				return $cardData;
			}
		}

		return false;
	}

	private function maxCardID($user_id='')
	{
		$cards = $this->getCards($user_id);

		if($cards->cards === []) return 0;

		$maxCardID = 0;

		foreach ($cards->cards as $cardData) {
			if($cardData->card_id > $maxCardID){
				$maxCardID = $cardData->card_id;
			}
		}

		return $maxCardID;
	}

	public function single_buy($shop_process_id,$amount){
				
		$amount = $amount.".00";
		$currency = "PYG";
		$this->token = md5($this->privateKey . $shop_process_id . $amount . $currency);
		
		$request = array(
			"public_key" => $this -> publicKey,
			"operation" => array(
				"token" => $this->token,
				"shop_process_id" => $shop_process_id,
				"amount" => $amount,
				"currency" => $currency,
				"additional_data" => "",
				"description" => $this->description,
				"return_url" => $this -> return_url,
				"cancel_url" => $this -> cancel_url
			)
		);

		$resp = $this->post("/vpos/api/0.3/single_buy",$request);
		
		if(@$resp->status == "success"){
			$this->process_id = $resp->process_id;
			return $resp->process_id;
		}else{
			$this->handleApiError($resp);
			return false;
		}		
	}
	
	public function getConfirmation($shop_process_id){
		
		/*
			OBTENER CONFIRMACIÓN: Solicita la confirmación de una transacción a la API enviando el ID interno y lo guarda en la tabla de pagos.
			
			Parámetros recibidos:
			
			$shop_process_id: Es el identificador de la transacción generada a través de un SINGLE BUY.
							  ATENCIÓN!!! El ID que debe enviarse es el de la tabla ´vpos´ generada por esta librería.
		*/
		
		$this->token = md5($this->privateKey . $shop_process_id . "get_confirmation");

		$request = array(
			"public_key" => $this -> publicKey,
			"operation" => array(
				"token" => $this->token,
				"shop_process_id" => $shop_process_id
			)
		);
		
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => $this -> url . "/vpos/api/0.3/single_buy/confirmations",
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => json_encode($request)
		));
		
		$resp = curl_exec($curl);
		curl_close($curl);
		
		if(is_string($resp)) $resp = json_decode($resp,true);
		
		if($resp['confirmation']['response_code'] == "00"){
			$resp['confirmation']['VpStatus'] = "aprobado";
		}else{
			$resp['confirmation']['VpStatus'] = "rechazado";
		}
		
		$this->bancard_model->confirm($resp['confirmation']);
		
		return $resp['confirmation'];
	}
	
	public function rollback($shop_process_id){
		
		/*
			ROLLBACK: Solicita la cancelación de una compra a la API enviando el ID interno y guarda la respuesta en la tabla de pagos.
			
			Parámetros recibidos:
			
			$shop_process_id: Es el identificador de la transacción generada a través de un SINGLE BUY.
							  ATENCIÓN!!! El ID que debe enviarse es el de la tabla ´vpos´ generada por esta librería.
		*/
		
				
		$this->token = md5($this->privateKey . $shop_process_id . "rollback" . "0.00");
		
		$request = array(
			"public_key" => $this -> publicKey,
			"operation" => array(
				"token" => $this->token,
				"shop_process_id" => $shop_process_id
			),
			"test_client" => true
		);
		
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => $this -> url . "/vpos/api/0.3/single_buy/rollback",
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => $request,
			CURLOPT_HTTPHEADER => 'Content-Type: application/json'
		));
		
		$resp = curl_exec($curl);
		
		if(is_string($resp)) $resp = json_decode($resp,true);
		
		curl_close($curl);
		
		$resp['shop_process_id'] = $shop_process_id;
		$resp['token'] = $this->token;
		
		$shop_process_id = $this->saveRollback($resp);
		
	}
	
 }

?>
