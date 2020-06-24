#/bin/bash
rsync -vza --exclude .git --exclude .idea --exclude .env /home/intra/dev/php/sell-and-sign-diruy/ root@192.168.10.6:/var/www/sell-and-sign-diruy/
ssh root@192.168.10.6  chown -R www-data /var/www/sell-and-sign-diruy
ssh root@192.168.10.6  chgrp -R www-data /var/www/sell-and-sign-diruy
