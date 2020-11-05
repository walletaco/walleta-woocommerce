<?php
if (!defined('ABSPATH')) {
    exit;
}

class Walleta_Http_Request
{
    /**
     * @param string $url Url
     * @param array $data Data
     */
    public function post($url, $data)
    {
        $jsonData = json_encode($data);

        $options = [
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonData)
            ],
        ];

        $curl = curl_init($url);
        curl_setopt_array($curl, $options);

        $body = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        return new Walleta_Http_Response($httpCode, $body);
    }
}