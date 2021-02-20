<?php

/*
//////////////////////////Ideamart, BDAPPS & MSPACE///////////////////// 
*
* Ideamart
*		*SMS Send: https://api.ideamart.io/sms/send
*		*USSD Send: https://api.ideamart.io/ussd/send
*		*Subscription: https://api.ideamart.io/subscription/send
*		*Get Status: https://api.ideamart.io/subscription/getStatus
*		*Query base: https://api.ideamart.io/subscription/query-base
*		*Location: https://api.ideamart.io/lbs/locate
*
* BDAPPS
*		*SMS Send: https://developer.bdapps.com/sms/send
*		*USSD Send: https://developer.bdapps.com/ussd/send
*		*Subscription: https://developer.bdapps.com/subscription/send
*		*Get Status: https://developer.bdapps.com/subscription/getstatus  //!important
*		*Query base: url not provided
*
* MSPACE
*		*SMS Send: https://api.mspace.lk/sms/send
*		*USSD Send: https://api.mspace.lk/ussd/send
*		*Subscription: https://api.mspace.lk/subscription/send
*		*Get Status: https://api.mspace.lk/subscription/getStatus
*		*Query base: https://api.mspace.lk/subscription/query-base
*		
*/

/********* Core Class **********/
class Core
{

    public function sendRequest($jsonStream, $url)
    {

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStream);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }
}


// date_default_timezone_set('Asia/Colombo');

// class Logger{
// 	public function WriteLog($logStream){
// 		$_LOGFILE = 'LogData.log';

// 		$file = fopen($_LOGFILE, 'w');
// 		fwrite($file, /*'['.date('D M j G:i:s T Y').'] '."\n".*/ $logStream. "\n");
// 		fclose($file);
// 	}
// }


# SMS
class SMSReceiver
{
    private $version;
    private $applicationId;
    private $sourceAddress;
    private $message;
    private $requestId;
    private $encoding;
    private $thejson;

    public function __construct()
    {

        $jsonRequest = json_decode(file_get_contents('php://input'));
        
        if(!(isset(
            $jsonRequest->version,	
            $jsonRequest->applicationId,
            $jsonRequest->address,
            $jsonRequest->message,
            $jsonRequest->requestId,
            $jsonRequest->encoding		
            ))) 
            
            if(!((isset($jsonRequest->sourceAddress) && isset($jsonRequest->message) )))
                $response = array('statusCode'=>'E1312', 'statusDetail'=>'Request is Invalid.');
        else{
            $this->thejson=$jsonRequest ;
            $this->version = $jsonRequest->version;
            $this->applicationId = $jsonRequest->applicationId;
            $this->sourceAddress = $jsonRequest->sourceAddress;
            $this->message = $jsonRequest->message;
            $this->requestId = $jsonRequest->requestId;
            $this->encoding = $jsonRequest->encoding;
                
            $response = array('statusCode'=>'S1000', 'statusDetail'=>'Process completed successfully.');
        }

        header('Content-type: application/json');
        echo json_encode($response);
    }

    // Get the version of the incomming message
    public function getVersion()
    {
        return $this->version;
    }

    // Get the encoding of the incomming message
    public function getEncoding()
    {
        return $this->encoding;
    }

    // Get the Application of the incomming message
    public function getApplicationId()
    {
        return $this->applicationId;
    }

    // Get the address of the incomming message
    public function getAddress()
    {
        return $this->sourceAddress;
    }

    // Get the Message of the incomming request	
    public function getMessage()
    {
        return $this->message;
    }

    // Get the unique requestId of the incomming message	
    public function getRequestId()
    {
        return $this->requestId;
    }

    // Get the json
    public function getJson()
    {
        return $this->thejson;
    }
}


class SMSSender  extends Core
{
    private $applicationId,
        $password,
        $charging_amount,
        $encoding,
        $version,
        $deliveryStatusRequest,
        $binaryHeader,
        $sourceAddress,
        $serverURL;

    public function __construct($serverURL, $applicationId, $password)
    {
        if (!(isset($serverURL, $applicationId, $password)))
            throw new SMSServiceException('Request Invalid.', 'E1312');
        else {
            $this->applicationId = $applicationId;
            $this->password = $password;
            $this->serverURL = $serverURL;
        }
    }

    // Broadcast a message to all the subcribed users
    public function broadcast($message)
    {
        return $this->sms($message, array('tel:all'));
    }

    // Send a message to the user with a address or send the array of addresses
    public function sms($message, $addresses)
    {
        if (empty($addresses))
            throw new SMSServiceException('Format of the address is invalid.', 'E1325');
        else {
            $jsonStream = (is_string($addresses)) ? $this->resolveJsonStream($message, array($addresses)) : (is_array($addresses) ? $this->resolveJsonStream($message, $addresses) : null);
            return ($jsonStream != null) ? $this->handleResponse($this->sendRequest($jsonStream, $this->serverURL)) : false;
        }
    }

    private function handleResponse($jsonResponse)
    {

        $statusCode = $jsonResponse->statusCode;
        $statusDetail = $jsonResponse->statusDetail;

        if (empty($jsonResponse))
            throw new SMSServiceException('Invalid server URL', '500');
        else if (strcmp($statusCode, 'S1000') == 0)
            return true;
        else
            throw new SMSServiceException($statusDetail, $statusCode);
    }

    private function resolveJsonStream($message, $addresses)
    {

        $messageDetails = array(
            "message" => $message,
            "destinationAddresses" => $addresses
        );

        if (isset($this->sourceAddress)) {
            $messageDetails = array_merge($messageDetails, array("sourceAddress" => $this->sourceAddress));
        }

        if (isset($this->deliveryStatusRequest)) {
            $messageDetails = array_merge($messageDetails, array("deliveryStatusRequest" => $this->deliveryStatusRequest));
        }

        if (isset($this->binaryHeader)) {
            $messageDetails = array_merge($messageDetails, array("binaryHeader" => $this->binaryHeader));
        }

        if (isset($this->version)) {
            $messageDetails = array_merge($messageDetails, array("version" => $this->version));
        }

        if (isset($this->encoding)) {
            $messageDetails = array_merge($messageDetails, array("encoding" => $this->encoding));
        }

        $applicationDetails = array(
            'applicationId' => $this->applicationId,
            'password' => $this->password,
        );

        $jsonStream = json_encode($applicationDetails + $messageDetails);

        return $jsonStream;
    }

    public function setsourceAddress($sourceAddress)
    {
        $this->sourceAddress = $sourceAddress;
    }

    public function setcharging_amount($charging_amount)
    {
        $this->charging_amount = $charging_amount;
    }

    public function setencoding($encoding)
    {
        $this->encoding = $encoding;
    }

    public function setversion($version)
    {
        $this->version = $version;
    }

    public function setbinaryHeader($binaryHeader)
    {
        $this->binaryHeader = $binaryHeader;
    }

    public function setdeliveryStatusRequest($deliveryStatusRequest)
    {
        $this->deliveryStatusRequest = $deliveryStatusRequest;
    }
}


class SMSServiceException extends Exception
{
    private $statusCode,
        $statusDetail;

    public function __construct($message, $code)
    {
        parent::__construct($message);

        $this->statusCode = $code;
        $this->statusDetail = $message;
    }

    public function getErrorCode()
    {
        return $this->statusCode;
    }

    public function getErrorMessage()
    {
        return $this->statusDetail;
    }
}


# USSD
class UssdReceiver
{

    private $sourceAddress;
    private $message;
    private $requestId;
    private $applicationId;
    private $encoding;
    private $version;
    private $sessionId;
    private $ussdOperation;
    private $vlrAddress;
    private $thejson;

    public function __construct()
    {
        $array = json_decode(file_get_contents('php://input'), true);
        $this->thejson = json_decode(file_get_contents('php://input'), true);
        $this->sourceAddress = $array['sourceAddress'];
        $this->message = $array['message'];
        $this->requestId = $array['requestId'];
        $this->applicationId = $array['applicationId'];
        $this->encoding = $array['encoding'];
        $this->version = $array['version'];
        $this->sessionId = $array['sessionId'];
        $this->ussdOperation = $array['ussdOperation'];

        if (!((isset($this->sourceAddress) && isset($this->message)))) {
            throw new Exception("Some of the required parameters are not provided");
        } else {
            $responses = array("statusCode" => "S1000", "statusDetail" => "Success");
        }
    }

    public function getthejson()
    {
        return $this->thejson;
    }

    public function getAddress()
    {
        return $this->sourceAddress;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getRequestID()
    {
        return $this->requestId;
    }

    public function getApplicationId()
    {
        return $this->applicationId;
    }

    public function getEncoding()
    {
        return $this->encoding;
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function getSessionId()
    {
        return $this->sessionId;
    }

    public function getUssdOperation()
    {
        return $this->ussdOperation;
    }
}


class UssdSender extends Core
{
    private $applicationId,
        $password,
        $charging_amount = '',
        $encoding = '',
        $version = '',
        $deliveryStatusRequest = '',
        $binaryHeader = '',
        $sourceAddress = '',
        $serverURL;

    public function __construct($serverURL, $applicationId, $password)
    {
        $this->serverURL = $serverURL;
        $this->applicationId = $applicationId;
        $this->password = $password;
    }

    public function ussd($sessionId, $message, $destinationAddress, $ussdOperation = 'mo-cont')
    {

        if (is_array($destinationAddress)) {
            return $this->ussdMany($message, $sessionId, $ussdOperation, $destinationAddress);
        } else if (is_string($destinationAddress) && trim($destinationAddress) != "") {
            return $this->ussdMany($message, $sessionId, $ussdOperation, $destinationAddress);
        } else {
            throw new Exception("address should a string or a array of strings");
        }
    }

    private function ussdMany($message, $sessionId, $ussdOperation, $destinationAddress)
    {

        $arrayField = array(
            "applicationId" => $this->applicationId,
            "message" => $message,
            "password" => $this->password,
            "sessionId" => $sessionId,
            "ussdOperation" => $ussdOperation,
            "destinationAddress" => $destinationAddress,
            "encoding" => "440"
        );

        $jsonObjectFields = json_encode($arrayField);
        return $this->sendRequest($jsonObjectFields, $this->serverURL);
    }

    public function handleResponse($resp)
    {
        if ($resp == "") {
            throw new UssdException("Server URL is invalid", '500');
        } else {
            echo $resp;
        }
    }
}



# Ussd Exception Handler
class UssdException extends Exception
{

    var $code;
    var $response;
    var $statusMessage;

    public function __construct($message, $code, $response = null)
    {
        parent::__construct($message);
        $this->statusMessage = $message;
        $this->code = $code;
        $this->response = $response;
    }

    public function getStatusCode()
    {
        return $this->code;
    }

    public function getStatusMessage()
    {
        return $this->statusMessage;
    }

    public function getRawResponse()
    {
        return $this->response;
    }
}


class DirectDebitSender extends core
{
    var $server;
    var $applicationId;
    var $password;


    public function __construct($server, $applicationId, $password)
    {
        $this->server = $server;
        $this->applicationId = $applicationId;
        $this->password = $password;
    }

    /*
        Get parameters form the application
        check one or more addresses
        Send them to cassMany
    **/
    public function cass($externalTrxId, $subscriberId, $amount)
    {

        if (is_array($subscriberId)) {
            return $this->cassMany($externalTrxId, $subscriberId,  $amount);
        } else if (is_string($subscriberId) && trim($subscriberId) != "") {
            return $this->cassMany($externalTrxId, $subscriberId,  $amount);
        } else {
            throw new Exception("Address should be a string or a array of strings");
        }
    }


    private function cassMany($externalTrxId, $subscriberId, $amount)
    {
        $arrayField = array(
            "applicationId" => $this->applicationId,
            "password" => $this->password,
            "externalTrxId" => $externalTrxId,
            "subscriberId" => $subscriberId,
            "amount" => $amount
        );
        $jsonObjectFields = json_encode($arrayField);
        return $this->handleResponse(json_decode($this->sendRequest($jsonObjectFields, $this->server)));
    }


    private function handleResponse($jsonResponse)
    {

        if (empty($jsonResponse))
            throw new CassException('Invalid server URL', '500');

        $statusCode = $jsonResponse->statusCode;
        $statusDetail = $jsonResponse->statusDetail;

        if (strcmp($statusCode, 'S1000') == 0)
            return 'ok';
        else
            throw new CassException($statusDetail, $statusCode);
    }
}


# Subscription
class Subscription extends Core
{

    public function __construct($sendURL, $getStatusURL, $baseURL)
    {
        $this->sendURL = $sendURL;
        $this->getStatusURL = $getStatusURL;
        $this->baseURL = $baseURL;
    }

    public function RegUser($applicationId, $password, $subscriberId)
    {
        $arrayField = array(
            "applicationId" => $applicationId,
            "password" => $password,
            "subscriberId" => $subscriberId,
            "action" => 1
        );

        $jsonObjectFields = json_encode($arrayField);
        return $this->sendRequest($jsonObjectFields, $this->sendURL);
    }

    public function UnregUser($applicationId, $password, $subscriberId)
    {
        $arrayField = array(
            "applicationId" => $applicationId,
            "password" => $password,
            "version" => "1.0",
            "action" => 0,
            "subscriberId" => $subscriberId
        );

        $jsonObjectFields = json_encode($arrayField);
        return $this->sendRequest($jsonObjectFields, $this->sendURL);
    }

    public function getStatus($applicationId, $password, $subscriberId)
    {
        $arrayField = array(
            "applicationId" => $applicationId,
            "password" => $password,
            "subscriberId" => $subscriberId
        );

        $jsonObjectFields = json_encode($arrayField);

        $resp = $this->sendRequest($jsonObjectFields, $this->getStatusURL);
        $response = json_decode($resp, true);

        $statusDetail = $response['statusDetail'];
        $statusCode = $response['statusCode'];
        $status = $response['subscriptionStatus'];

        return $status;
    }

    public function getBaseSize($applicationId, $password)
    {
        $arrayField = array(
            "applicationId" => $applicationId,
            "password" => $password
        );

        $jsonObjectFields = json_encode($arrayField);
        $resp = $this->sendRequest($jsonObjectFields, $this->baseURL);
        $response = json_decode($resp, true);

        $statusDetail = $response['statusDetail'];
        $statusCode = $response['statusCode'];
        $status = $response['baseSize'];

        return $status;
    }

    // Test
    public function RegUserTest($applicationId, $password, $subscriberId)
    {
        $arrayField = array(
            "applicationId" => $applicationId,
            "password" => $password,
            "subscriberId" => $subscriberId,
            "action" => 1
        );

        $jsonObjectFields = json_encode($arrayField);
        $resp = $this->sendRequest($jsonObjectFields, $this->sendURL);
        $response = json_decode($resp, true);
        $status = $response['subscriptionStatus'];
        return $status;
    }
}


# Location (Only for ideamart)

class Location
{

    public function getLocation($applicationId, $password, $subscriberId)
    {
        $arrayField = array(
            "applicationId" => $applicationId,
            "password" => $password,
            "subscriberId" => $subscriberId,
            "serviceType" => "IMMEDIATE"
        );

        $jsonObjectFields = json_encode($arrayField);

        $ch = curl_init('https://api.ideamart.io/lbs/locate');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonObjectFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($res, true);
        return $response;
    }
}


class CassException extends Exception
{ //Cass Exception Handler

    var $code;
    var $response;
    var $statusMessage;

    public function __construct($message, $code, $response = null)
    {
        parent::__construct($message);
        $this->statusMessage = $message;
        $this->code = $code;
        $this->response = $response;
    }

    public function getStatusCode()
    {
        return $this->code;
    }

    public function getStatusMessage()
    {
        return $this->statusMessage;
    }

    public function getRawResponse()
    {
        return $this->response;
    }
}

?>