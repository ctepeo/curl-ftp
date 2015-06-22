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
        'threads' => 5,
        'timeout_request' => 10,
        'active_mode' => false
    );
    //  download queue 
    private $queue = array();
    //  handlers
    public $connection = false;
    public $curl = false;
    public $threads = array();
    public $multiThreads = array();
    public $local = "/";

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
        $this->configuration['ftp'] = "ftp://" . $this->configuration['user'] . ":" . $this->configuration['password'] . '@' . $this->configuration['host'] . ":" . $this->configuration['port'] . '/';
    }

    public function _get($key) {
        return $this->configuration[$key];
    }

    /* Helpers */

    function _correctPath($path) {
        if (mb_substr($path, 0, 1) == "/")
            $path = mb_substr($path, 1, mb_strlen($path));
        return $path;
    }

    function _getCHMOD($string) {
        $result = array();
        //  remove sticky bit
        foreach (str_split(substr($string, 1, strlen($string) - 1), 3) as $chunk) {
            $id = count($result);
            $access = 0;
            $mask = str_split($chunk, 1);
            if ($mask[0] == "r")
                $access = $access + 4;
            if ($mask[1] == "w")
                $access = $access + 2;
            if ($mask[2] == "x")
                $access = $access + 1;
            $result[$id] = $access;
        }
        return (int) implode('', $result);
    }

    function _isDir($path) {
        if (mb_substr($path, mb_strlen($path) - 1, 1) == "/")
            return true;
        return false;
    }

    function _prepareLocal($directories = array(), $base = '/') {
        if (!empty($directories) && isset($directories['content']) && !empty($directories['content'])) {
            foreach ($directories['content'] as $folder => $content) {
                //d($content);
                if ($content['type'] == 'dir') {
                    if (!file_exists($base . $content['name'] . '/') || !is_dir($base . $content['name'])) {
                        mkdir($base . $content['name'] . '/', 0777);
                    }
                    if ($content['content'])
                        $this->_prepareLocal($content, $base . $content['name'] . '/');
                }
            }
        }
    }

    function _addDirToQueue($directory = array(), $base = '/') {
        if (empty($directory['content']))
            return true;
        foreach ($directory['content'] as $name => $data) {
            if ($data['type'] == 'file') {
                $this->queue[] = array(
                    'remote' => $data['path'],
                    'local' => $base . $data['name'],
                    'chmod' => $data['chmod']
                );
            } else {
                $this->_addDirToQueue($data, $base . $data['name'] . '/');
            }
        }
    }

    function _processQueue() {
        if (empty($this->queue))
            return true;
        $this->threads = array();
        $x = 0;
        foreach ($this->queue as $qid => $request) {
            if ($x >= $this->configuration['threads'])
                break;
            $id = count($this->threads);
            $this->threads[count($this->threads)] = curl_init();
            $this->_ftp_connect($this->threads[$id], $request['remote'], true);
            $file = fopen($this->local . $request['local'], 'w');
            curl_setopt($this->threads[$id], CURLOPT_URL, $this->configuration['ftp'] . $request['remote']);
            curl_setopt($this->threads[$id], CURLOPT_BINARYTRANSFER, TRUE);
            curl_setopt($this->threads[$id], CURLOPT_FILE, $file);
            unset($this->queue[$qid]);
            $x++;
        }
        $this->multiThreads = curl_multi_init();
        foreach ($this->threads as $thread) {
            curl_multi_add_handle($this->multiThreads, $thread);
        }
        $active = null;
        //   process them
        do {
            $mrc = curl_multi_exec($this->multiThreads, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        while ($active) {
            if (curl_multi_select($this->multiThreads) != -1) {
                do {
                    $mrc = curl_multi_exec($this->multiThreads, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }
        // close 
        foreach ($this->threads as $thread) {
            curl_multi_remove_handle($this->multiThreads, $thread);
        }
        curl_multi_close($this->multiThreads);
        if (!empty($this->queue))
            $this->_processQueue();
        return true;
    }

    function _setChmod($directory, $path = '/') {
        if ($directory['content']) {
            foreach ($directory['content'] as $id => $xdata) {
                switch ($xdata['type']) {
                    case 'file':
                        chmod($this->local . $path . $xdata['name'], octdec("0" . $xdata['chmod']));
                        break;
                    case 'dir':
                        chmod($this->local . $path . $xdata['name'] . "/", octdec("0" . $xdata['chmod']));
                        $this->_setChmod($xdata, ($path == '/' || $path == '' ? "" : $path) . $xdata['name'] . "/");
                        break;
                }
            }
        }
    }

    /* cURL function */

    public function _ftp_connect($threadid = false, $path = false, $initOnly = false) {

        if ($threadid == false) {
            $thread = $this->curl;
        } else {
            $thread = $threadid;
        }

        curl_setopt($thread, CURLOPT_URL, $path ? $path : $this->configuration['ftp']);
        $this->configuration['log'] = fopen('php://temp', 'rw+');
        curl_setopt($thread, CURLOPT_VERBOSE, 1);
        curl_setopt($thread, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($thread, CURLOPT_CONNECTTIMEOUT, $this->configuration['timeout_connection']);
        curl_setopt($thread, CURLOPT_TIMEOUT, $this->configuration['timeout_request']);
        curl_setopt($thread, CURLOPT_STDERR, $this->configuration['log']);
        curl_setopt($thread, CURLINFO_HEADER_OUT, $this->configuration['debug']);
        if ($this->configuration['ssl'] == false) {
            curl_setopt($thread, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($thread, CURLOPT_SSL_VERIFYPEER, FALSE);
        }
        if ($initOnly)
            return true;
        $response = curl_exec($thread);
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

    public function parseDirList($listing, $order, $path = '/') {
        $files = array();
        $folders = array();
        foreach (array_filter(explode(PHP_EOL, $listing)) as $id => $line) {
            $items = array_filter(explode(" ", $line));
            end($items);
            $key = key($items);
            $name = $items[$key];
            $size = (int) $items[array_keys($items)[4]];
            if (mb_substr($items[0], 0, 1) == "d" || mb_substr($items[0], 0, 1) == "l") {
                //  directory
                if ((mb_substr($items[0], 0, 1) == "l" && !isset($folders[$name])) || mb_substr($items[0], 0, 1) == "d")
                    $folders[$name] = array(
                        'name' => $name,
                        'path' => '/' . $path . $name . '/',
                        'size' => FALSE,
                        'type' => 'dir',
                        'chmod' => $this->_getCHMOD($items[0]),
                        'content' => false
                    );
            } else {
                //  file
                $files[$name] = array(
                    'name' => $name,
                    'path' => '/' . $path . $name,
                    'size' => $size,
                    'type' => 'file',
                    'chmod' => $this->_getCHMOD($items[0])
                );
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
    public function dir($path = '/', $order = 'default') {
        //  correct path
        $path = $this->_correctPath($path);
        //  check connection
        if ($this->connect() || $this->connect(true)) {
            curl_setopt($this->curl, CURLOPT_URL, $this->configuration['ftp'] . $path);
            $response = $this->_ftp_query("LIST", TRUE);
            if (!$response)
                return false;
            return array(
                'type' => 'dir',
                'chmod' => false,
                'content' => $this->parseDirList($response, $order, $path));
        } else {
            return false;
        }
    }

    public function dirTree($path = '/', $dirinfo = false) {
        $result = array();
        $dir = $this->dir($path);
        if ($dir == false)
            return array(
                'name' => $dirinfo ? $dirinfo['name'] : false,
                'path' => $path,
                'type' => 'dir',
                'chmod' => $dirinfo ? $dirinfo['chmod'] : false,
                'content' => false
            );
        $result = array(
            'name' => $dirinfo ? $dirinfo['name'] : false,
            'path' => $path,
            'type' => $dir['type'],
            'chmod' => $dirinfo ? $dirinfo['chmod'] : false,
            'content' => false
        );
        foreach ($dir['content'] as $id => $file) {
            if ($file['type'] == 'file')
                $tmp[] = $file;
            if ($file['type'] == 'dir')
                $tmp[] = $this->dirTree($file['path'], $file);
        }
        $result['content'] = $tmp;
        return $result;
    }

    //  Download single file from $remote server to $local host
    public function download_file($remote, $local) {
        if ($this->connect() || $this->connect(true)) {
            $file = fopen($local, 'w');
            curl_setopt($this->curl, CURLOPT_URL, $this->configuration['ftp'] . $remote);
            curl_setopt($this->curl, CURLOPT_BINARYTRANSFER, TRUE);
            curl_setopt($this->curl, CURLOPT_FILE, $file);
            $response = curl_exec($this->curl);
            fclose($file);
            if ($response == false)
                unlink($local);
            return $response;
        } else {
            return false;
        }
    }

    // Download file/files or directories, depends on $remote
    // $remote can be an array of files/directories or single file/directory
    // $local is a path to save files/directories from $remote
    public function download($remote, $local) {
        if (is_array($remote)) {
            foreach ($remote as $rmt) {
                if ($this->_isDir($rmt)) {
                    $directory = $this->dirTree($rmt);
                    if (!empty($directory)) {
                        $this->_prepareLocal($directory, $local);
                        $this->_addDirToQueue($directory);
                    }
                } else {
                    if (is_dir($local)) {
                        $rpath = explode("/", $remote);
                        $local = $local . $rpath[count($rpath) - 1];
                    }
                    $this->queue[] = array(
                        'local' => $local,
                        'remote' => $remote,
                        'chmod' => 644
                    );
                }
            }
        } else {
            if ($this->_isDir($remote)) {
                $directory = $this->dirTree($remote);
                if (!empty($directory)) {
                    $this->_prepareLocal($directory, $local);
                    $this->_addDirToQueue($directory);
                }
            } else {
                if (is_dir($local)) {
                    $rpath = explode("/", $remote);
                    $local = $local . $rpath[count($rpath) - 1];
                }
                $this->queue[] = array(
                    'local' => $local,
                    'remote' => $remote,
                    'chmod' => 644
                );
            }
        }
        if (!is_writable(dirname($local))) {
            die('Directory ' . dirname($local) . ' must be writeable');
        }
        $this->local = $local;
        if (!empty($this->queue)) {
            $this->_processQueue();
            //   set chmod to downloaded files and directories
            if ($directory) {
                $this->_setChmod($directory, '');
            }
        }
        return true;
    }

    function upload_file($local, $remote) {
        if (!file_exists($local)) {
            die('Local file ' . $local . ' doesn\'t exist');
        }
        curl_setopt($this->curl, CURLOPT_URL, $this->configuration['ftp'] . $remote . basename($local));
        $fp = fopen($local, 'r');
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->curl, CURLOPT_UPLOAD, 1);
        curl_setopt($this->curl, CURLOPT_INFILE, $fp);
        curl_setopt($this->curl, CURLOPT_INFILESIZE, filesize($local));

        $response = curl_exec($this->curl);
        if (curl_errno($this->curl)) {
            return false;
        }
        return true;
    }

    function delete($remote) {
        // get folder
        $folder = explode("/", $remote);
        unset($folder[count($folder) - 1]);
        $folder = implode("/", $folder) . "/";
        curl_setopt($this->curl, CURLOPT_URL, $this->configuration['ftp'] . $folder);
        if ($this->_isDir($remote)) {
            $response = $this->_ftp_query('RMD ' . $remote, true);
        } else {
            $response = $this->_ftp_query('DELE ' . $remote, true);
        }
        return true;
    }

    function createDir($path) {
        curl_setopt($this->curl, CURLOPT_URL, $this->configuration['ftp'] . $path);
        curl_setopt($this->curl, CURLOPT_FTP_CREATE_MISSING_DIRS, TRUE);
        $response = curl_exec($this->curl);
        return $response;
    }

}

?>