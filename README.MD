# Web site Log Listener - Dockerfile

Advanced Networking with PHP & MySQL

### Create our database Dockerfile in ./db

```
FROM mysql:8.0

```

### Create our web Dockerfile in ./www

```
FROM php:7.3-apache

RUN docker-php-ext-install mysqli pdo_mysql
RUN docker-php-ext-enable mysqli pdo_mysql
```

### Setup our network in the docker compose file

```
version: "3"

networks:
  log-listener-frontend:
    driver: bridge
    ipam:
      driver: default
      config:
        - subnet: 172.10.1.0/24
  log-listener-backend:
    driver: bridge
    ipam: 
      driver: default
      config:
        - subnet: 172.10.2.0/23
```

### Create a database environment file

```
MYSQL_DATABASE=sys_db
MYSQL_USER=sys_admin
MYSQL_PASSWORD=sys_password
MYSQL_ROOT_PASSWORD=root_password
```

### Add the database container

```
services:
  log-listener-db:
    build: ./db
    command: --default-authentication-plugin=mysql_native_password
    ports:
      - 3306:3306
    networks:
      log-listener-backend:
        ipv4_address: 172.10.3.2
    env_file:
      - ./development.env
```

### Add the web container

```
  log-listener-www:
    build: ./www
    ports:
      - 8080:80
    volumes:
      - ./src:/var/www/html/
    networks:
      log-listener-backend:
        ipv4_address: 172.10.2.2
      log-listener-frontend:
        ipv4_address: 172.10.1.2
    depends_on:
      - log-listener-db
    env_file:
      - ./development.env
```


### Run the demo

```
docker-compose up -d
```

Visiting `http://localhost:8080` or `local_ip:8080`


### To destroy the setup

```
docker-compose down
```

### Resources

* [Docker - Network](https://docs.docker.com/network/)
* [Docker - Environment Variables](https://docs.docker.com/compose/environment-variables/)
