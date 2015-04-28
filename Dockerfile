FROM ubuntu:latest

# upgrade debian packages
ENV DEBIAN_FRONTEND noninteractive
RUN apt-get update \
  && apt-get -y dist-upgrade \
  && apt-get install --no-install-recommends -y \
    curl \
    ca-certificates \
    git \
    php5-cli \
    php5-mysql \
    php5-gd \
  && apt-get -y clean
ENV DEBIAN_FRONTEND newt

# install wordpress
RUN mkdir -p /usr/src/wordpress \
    && curl https://wordpress.org/latest.tar.gz > /usr/src/wordpress/latest.tar.gz \
    && mkdir -p ~/.cache/whippet/wordpresses/latest \
    && tar -C ~/.cache/whippet/wordpresses/latest -xzf /usr/src/wordpress/latest.tar.gz \
    && mv ~/.cache/whippet/wordpresses/latest/wordpress/* ~/.cache/whippet/wordpresses/latest \
    && rmdir ~/.cache/whippet/wordpresses/latest/wordpress \
    && rm /usr/src/wordpress/latest.tar.gz

# install whippet-server
COPY . /usr/src/whippet-server
RUN git -C /usr/src/whippet-server submodule update --init --recursive \
  && ln -s /usr/src/whippet-server/whippet-server /usr/local/bin/whippet-server

# set up for inheriting projects
ONBUILD COPY . /usr/src/app
ONBUILD WORKDIR /usr/src/app

# set up running environment for whippet
EXPOSE 80
CMD ["whippet-server", "-i", "0.0.0.0", "-p", "80", "--show-wp-errors", "--siteurl=http://localhost"]
