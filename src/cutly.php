<?php

class cutURL
{

    public $_key;
    public $_method;

    function __construct($method = "file_get_contents")
    {

        //set you API key
        $this->_key = $_SERVER['CUTTLY_TOKEN'];
        $this->_method = $method;
    }

    //returnValue options
    # "status"  "fullLink"  "date"  "shortLink"  "title"
    function cut($urlToCut, $shorName = "")
    {

        if (empty($shorName)) {
            $shorName = $this->generateRandomString();
        }

        $urlToCut = urlencode($urlToCut);
        $urlApiCuttly = $_SERVER['CUTTLY_API_URL'] . "?key=$this->_key&short=$urlToCut&name=$shorName";
        $json = $this->callURL($urlApiCuttly);
        $data = json_decode($json, true);
        return  $data["url"];
    }

    private function callURL($url)
    {

        if ($this->_method == "file_get_contents") {

            return file_get_contents($url);
        } elseif ($this->_method == "curl") {

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $output = curl_exec($ch);
            curl_close($ch);
            return $output;
        }
    }

    function generateRandomString($length = 6)
    {
        $tab = '';

        for ($i = 0; $i < 100; $i++) {
            $tab .= "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        }

        return substr(str_shuffle($tab), 0, $length);
    }
}
