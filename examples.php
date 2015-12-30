<?php
	
	/*
	╔════════════════════════════════════════════════╗
	║           INSTALACIÓN Y CONFIGURACIÓN          ║
	╚════════════════════════════════════════════════╝
	*/
	
	// Cargamos dependencias
	require_once("simplepdo.php");
	require_once("bancard.php");
	
	// Establecemos parámetros para SimplePDO
	$options = array( 
		"type" => "mysql",							// El tipo de conexion. Por defecto es "mysql". Opcional
		"host" => "localhost",						// Dominio o IP del servidor de la BD. Por defecto es "localhost". Opcional
		"user" => "root",							// Requerido
		"pass" => "123",							// Requerido
		"debug" => true,							// Modo Debug, muestra la consulta realizada en caso de error. Por defecto esta en false. Opcional
		"database" => "vpos_test"					// Nombre de la base de datos. Requerido
	);
	
	// Creamos una nueva instancia
	$DB = new SimplePDO($info);

	// Definimos la url raíz del sitio
	define("ROOT_DIR","http://".$_SERVER['SERVER_NAME']."/");
	
	// Una vez que tengamos todo listo, instanciamos Bancard vPos
	$bancard = new Bancard();
	
	// Un poco de debug
	var_dump($bancard);
	
	
	
	
	/*
	╔════════════════════════════════════════════════╗
	║              EJEMPLO - SINGLE BUY              ║
	╚════════════════════════════════════════════════╝
	*/
	
	// Hacemos una solicitud de single_buy por Gs. 50.000 que pagará el usuario con ID 5
	$script = $bancard -> single_buy(5,50000);
	
	// La clase devuelve el script que redirecciona a la plataforma de pagos, lo imprimimos para que ejecute
	echo $script;
	
	
	
	/*
	╔════════════════════════════════════════════════╗
	║             EJEMPLO - CONFIRMACION             ║
	╚════════════════════════════════════════════════╝
	*/
	
	// Recuperamos el objeto enviado por Bancard 
	$response = file_get_contents('php://input');
	
	// Lo convertimos en un array
	$response = json_decode($response,true);
	
	// Evaluamos el código de respuesta y asignamos un status
	if($response['operation']['response_code'] == "00"){
		$response['operation']['VpStatus'] = "aprobado";
	}else{
		$response['operation']['VpStatus'] = "rechazado";
	}
	// Guardamos todos los datos en la base de datos
	$bancard -> confirm($response['operation']);
	
	
	
	/*
	╔════════════════════════════════════════════════╗
	║         EJEMPLO - OBTENER CONFIRMACION         ║
	╚════════════════════════════════════════════════╝
	*/
	
	// Para recuperar el resultado de una transacción llamamos a este método pasándole el ID de la transacción
	$confirmation = $bancard -> getConfirmation($VpCod);
	
	
	
	/*
	╔════════════════════════════════════════════════╗
	║               EJEMPLO - ROLLBACK               ║
	╚════════════════════════════════════════════════╝
	*/
	
	// Para solicitar el rollback de una transacción llamamos a este método pasándole el ID de la transacción
	$bancard -> rollback($VpCod);
	
	
?>