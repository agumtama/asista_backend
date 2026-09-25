Set-Location $PSScriptRoot
& php -d upload_max_filesize=5M -d post_max_size=48M -S 127.0.0.1:8000 -t public server-local.php
