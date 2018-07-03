FROM php:7.2-fpm-alpine

MAINTAINER "Nicolas Giraud" <nicolas.giraud@aareon.com>

COPY Standards/CodeSnifferExtended /data/Standards/CodeSnifferExtended

RUN rm -rf /data/Standards/CodeSnifferExtended/Docs \
    && curl -Ls https://squizlabs.github.io/PHP_CodeSniffer/phpcs.phar > /usr/local/bin/phpcs \
    && chmod +x /usr/local/bin/phpcs \
    && curl -Ls https://squizlabs.github.io/PHP_CodeSniffer/phpcbf.phar > /usr/local/bin/phpcbf \
    && chmod +x /usr/local/bin/phpcbf \

    && phpcs --config-set installed_paths /data/Standards/CodeSnifferExtended \
    && phpcs --config-set default_standard CodeSnifferExtended \
    && phpcs --config-set encoding utf-8 \
    && rm -rf /var/cache/apk/* /var/tmp/* /tmp/*

VOLUME ["/data"]
WORKDIR /data/www

ENTRYPOINT ["phpcs"]
CMD ["--version"]
