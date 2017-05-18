#!/bin/bash
docker exec -it module_testing bash -c "test -d /var/www/html/sites/simpletest || mkdir /var/www/html/sites/simpletest && chmod 777 /var/www/html/sites/simpletest"
docker exec -it module_testing bash -c "test -d /var/www/html/sites/default/files/simpletest || mkdir -p /var/www/html/sites/default/files/simpletest && chmod -R 777 /var/www/html/sites/default/files"
