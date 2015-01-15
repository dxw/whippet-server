FROM ubuntu

ENV DEBIAN_FRONTEND noninteractive
RUN apt-get update && apt-get -y dist-upgrade

RUN apt-get install --no-install-recommends -y wget ca-certificates
RUN apt-get install --no-install-recommends -y php5-cli php5-mysql

ADD . /whippet-server
RUN ln -s /whippet-server/whippet-server /usr/local/bin/whippet-server

VOLUME /app
EXPOSE 80
CMD whippet-server -i 0.0.0.0 -p 80 --show-wp-errors --siteurl=http://localhost /app
