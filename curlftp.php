<?php

/*
  FTP via cURL library for CodeIgniter
  created by George Sazanovich (ctepeo)

  Please be free to ask and contribute via github
  https://github.com/ctepeo/curl-ftp/

  This code distributed with The MIT License (MIT), so be free to use it anywhere

 */

class Curlftp {

    //  configurations
    private $configuration = array(
        'host' => 'localhost',
        'user' => 'root',
        'password' => '',
        'port' => 21,
        'ftp' => 'ftp://root:@localhost:21/',
        //  library config
        'ssl' => false,
        'debug' => true,
        'log' => "",
        'timeout_connection' => 10,
        'timeout_request' => 10,
        'active_mode' => false
    );
    //  handlers
    public $connection = false;
    public $curl = false;

    //  params must be an array with configuration. 
    //  at least 'host','user','password' to override default values
    public function __construct($params = array()) {
        $this->_set($params);
        $this->curl = curl_init();
    }

    /* OOP */

    public function _set($key = false, $value = false) {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->configuration[$k] = $v;
            }
        } else {
            $this->configuration[$key] = $value;
        }
        $this->configuration['ftp'] = "ftp://" . $this->configuration['user'] . ":" . $this->configuration['password'] . '@' . $this->configuration['host'] . '/';
    }

    public function _get($key) {
        return $this->configuration[$key];
    }

    /* cURL function */

    public function _ftp_connect() {

        curl_setopt($this->curl, CURLOPT_URL, $this->configuration['ftp']);
        $this->configuration['log'] = fopen('php://temp', 'rw+');
        curl_setopt($this->curl, CURLOPT_VERBOSE, 1);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, $this->configuration['timeout_connection']);
        curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->configuration['timeout_request']);
        curl_setopt($this->curl, CURLOPT_STDERR, $this->configuration['log']);
        curl_setopt($this->curl, CURLINFO_HEADER_OUT, $this->configuration['debug']);
        if ($this->configuration['ssl'] == false) {
            curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        }
        $response = curl_exec($this->curl);
        if ($response !== false)
            return true;
        $this->connection = true;
        return $response;
    }

    public function _ftp_query($query, $execute = false) {
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $query);
        if ($execute)
            return curl_exec($this->curl);
        return true;
    }

    /* FTP function */

    public function connect($force = false) {
        if ($this->connection != false && $force == false)
            return true;
        if ($this->_ftp_connect())
            return true;
        return false;
    }

    public function disconnect() {
        curl_close($this->curl);
    }

    public function parseDirList($listing, $order) {
        $files = array();
        $folders = array();
        foreach (array_filter(explode(PHP_EOL, $listing)) as $id => $line) {
            $items = array_filter(explode(" ", $line));
            end($items);
            $key = key($items);
            $name = $items[$key];
            $size = $items[array_keys($items)[4]];
            if (mb_substr($items[0], 0, 1) == "d") {
                //  directory
                $folders[$name] = FALSE;
            } else {
                //  file
                $files[$name] = $size;
            }
        }
        $result = array();
        //  sort
        switch ($order) {
            case 'default':
                ksort($folders, SORT_STRING);
                ksort($files, SORT_STRING);
                $result = array_merge($folders, $files);
                break;
            case 'name':
                $result = array_merge($folders, $files);
                ksort($result, SORT_STRING);
                break;
            case 'rname':
                $result = array_merge($folders, $files);
                krsort($result, SORT_STRING);
                break;
        }
        return $result;
    }

    /* Actions */

    //  Directory Listing
    //  ---
    //  Return directory list if directory exist. FALSE if not.
    //  Accepts path to folder as first argument. With or without starting slash
    //  Accepts result sorting as second argument(string). Available sort types:
    //  
    //  default - order by Name ASC, folders first, files after
    //  name - order by Name ASC, mixing folders and files
    //  rname - order by Name DESC, mixing folders and files
    public function dir($path = '/web/', $order = 'default') {
        //  correct path
        if (mb_substr($path, 0, 1) == "/")
            $path = mb_substr($path, 1, mb_strlen($path));
        //  check connection
        if ($this->connect() || $this->connect(true)) {
            curl_setopt($this->curl, CURLOPT_URL, $this->configuration['ftp'] . $path);
            $response = $this->_ftp_query("LIST", TRUE);
            if (!$response)
                return false;
            return $this->parseDirList($response, $order);
        } else {
            return false;
        }
    }

}

?>