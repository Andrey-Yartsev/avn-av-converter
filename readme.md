## Установка

### Composer

    $ curl -sS https://getcomposer.org/installer | php
    $ mv composer.phar /usr/local/bin/composer

### Зависимости

    $ composer install --no-dev
    
    
### Nginx

```converter.retloko.com -> /web```

### Crons

    $ php console/index.php amazon:queue
    $ php console/index.php amazon:upload