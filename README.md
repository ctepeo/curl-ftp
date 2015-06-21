# curl-ftp
FTP via cURL library for CodeIgniter

Usage: 

# Loading library
```sh
$this->load->library('curlftp',
     array(
        'host' => 'your_ftp_host_address',
        'user' => 'ftp_username',
        'password' => 'ftp_password'
    )
);
```
# Get directory's listing
```sh
$directory = $this->curlftp->dir('/public_html/');
```
# Download single file
```sh
$result = $this->curlftp->download_file('/public_html/index.php','/local/path/');
```
Will download file to /local/path/index.php and return true/false as result

# Download array of files 
```sh
$result = $this->curlftp->download(array('/public_html/index.php','/public_html/another.php'),'/local/path/');
```
Will download two files (index.php and another.php) to /local/path/index.php and /local/path/another.php and return true/false as result

# Download file and save renamed
```sh
$result = $this->curlftp->download('/public_html/index.php','/local/path/edited.php');
```
Will download remote file (/public_html/index.php) to /local/path/edited.php and return true/false as result

# Download directories and files recursively
```sh
$result = $this->curlftp->download('/public_html/','/local/path/'); 
```
Will download all files from /public_html/ and directories recursively to /local/path/ with chmod from remote server and return true/false as result. Please check $configuration['threads'] to define amount of connections



