FROM ubuntu:xenial

# upgrade debian packages
ENV DEBIAN_FRONTEND noninteractive
RUN apt-get update \
    && apt-get -y dist-upgrade \
    && apt-get install --no-install-recommends -y \
      wget \
      ca-certificates \
      git \
      php7.0-cli \
      php7.0-mysql \
      php7.0-gd \
      php7.0-curl \
    && apt-get -y clean
ENV DEBIAN_FRONTEND newt

# install wordpress
RUN mkdir -p /usr/src/wordpress \
    && wget https://wordpress.org/latest.tar.gz -O /usr/src/wordpress/latest.tar.gz \
    && mkdir -p ~/.cache/whippet/wordpresses/latest \
    && tar -C ~/.cache/whippet/wordpresses/latest -xzf /usr/src/wordpress/latest.tar.gz \
    && mv ~/.cache/whippet/wordpresses/latest/wordpress/* ~/.cache/whippet/wordpresses/latest \
    && rmdir ~/.cache/whippet/wordpresses/latest/wordpress \
    && rm /usr/src/wordpress/latest.tar.gz

# install composer
RUN wget --quiet https://getcomposer.org/composer.phar -O /usr/local/bin/composer \
    && chmod 755 /usr/local/bin/composer

# install whippet-server
COPY . /usr/src/whippet-server
RUN cd /usr/src/whippet-server \
    && composer install \
    && ln -s /usr/src/whippet-server/whippet-server /usr/local/bin/whippet-server

# set up for inheriting projects
ONBUILD COPY . /usr/src/app
ONBUILD WORKDIR /usr/src/app

# set up running environment for whippet
EXPOSE 80
CMD ["whippet-server", "-i", "0.0.0.0", "-p", "80", "--show-wp-errors", "--siteurl=http://localhost"]
