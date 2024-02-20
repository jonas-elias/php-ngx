# Use an official Ubuntu as a base image
FROM ubuntu:latest


ARG DEBIAN_FRONTEND=noninteractive

# Install dependencies
RUN apt-get update -yqq && apt-get install -yqq software-properties-common && \
    LC_ALL=C.UTF-8 add-apt-repository ppa:ondrej/php && \
    apt-get update -yqq && \
    apt-get install -yqq wget git unzip libxml2-dev cmake make systemtap-sdt-dev \
                    zlib1g-dev libpcre3-dev libargon2-0-dev libsodium-dev \
                    php8.2-cli php8.2-dev libphp8.2-embed php8.2-pgsql

# Clone ngx_php7 repository
# RUN git clone https://github.com/rryqszq4/ngx_php7.git

ENV NGINX_VERSION 1.25.3

ADD . .

WORKDIR /

RUN git clone -b v0.0.28 --single-branch --depth 1 https://github.com/rryqszq4/ngx-php.git

# Download and extract nginx
RUN wget -q http://nginx.org/download/nginx-${NGINX_VERSION}.tar.gz && \
    tar -zxf nginx-${NGINX_VERSION}.tar.gz && \
    cd nginx-${NGINX_VERSION} && \
    export PHP_LIB=/usr/lib && \
    ./configure --user=www --group=www \
                --prefix=/nginx \
                --with-ld-opt="-Wl,-rpath,$PHP_LIB" \
                --add-module=/ngx-php/third_party/ngx_devel_kit \
                --add-module=/ngx-php && \
    make && \
    make install

# RUN sed -i "s|app.php|app-pg.php|g" /deploy/nginx.conf

# RUN export WORKERS=$(( 3 )) && \
# RUN export WORKERS=$(( 1 )) && \
    # sed -i "s|worker_processes  auto|worker_processes $WORKERS|g" /deploy/nginx.conf
RUN sed -i "s|opcache.jit=on|opcache.jit=function|g" /etc/php/8.2/embed/conf.d/10-opcache.ini
EXPOSE 8080


CMD /nginx/sbin/nginx -c /deploy/nginx.conf
