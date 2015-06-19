# curl-ftp
FTP via cURL library for CodeIgniter

Usage: 

```sh
$this->load->library('curlftp',
     array(
        'host' => 'your_ftp_host_address',
        'user' => 'ftp_username',
        'password' => 'ftp_password'
    )
);

// Get directory's listing
$directory = $this->curlftp->dir('/public_html/');
var_dump($directory);
/* 
  name => filesize,   // if filesize is not FALSE - this is a file
                      // if filesize is FALSE - this is a directory
*/
```