FROM ubuntu

ENV DEBIAN_FRONTEND noninteractive
RUN apt-get update && apt-get -y dist-upgrade

# get deps
RUN apt-get install --no-install-recommends -y wget ca-certificates
RUN apt-get install --no-install-recommends -y php5-cli php5-mysql

# get WP
RUN mkdir /src
RUN wget https://wordpress.org/latest.tar.gz -O /src/latest.tar.gz
RUN mkdir -p ~/.cache/whippet/wordpresses/latest
RUN tar -C ~/.cache/whippet/wordpresses/latest -xzf /src/latest.tar.gz
RUN mv ~/.cache/whippet/wordpresses/latest/wordpress/* ~/.cache/whippet/wordpresses/latest
RUN rmdir ~/.cache/whippet/wordpresses/latest/wordpress

# install whippet-server
ADD . /whippet-server
RUN ln -s /whippet-server/whippet-server /usr/local/bin/whippet-server

VOLUME /app
EXPOSE 80
CMD whippet-server -i 0.0.0.0 -p 80 --show-wp-errors --siteurl=http://localhost /app
