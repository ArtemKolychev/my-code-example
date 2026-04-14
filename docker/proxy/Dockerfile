FROM alpine:3.19
RUN apk add --no-cache gcc musl-dev make git \
    && git clone --depth=1 https://github.com/rofl0r/microsocks /tmp/microsocks \
    && cd /tmp/microsocks && make \
    && mv microsocks /usr/local/bin/ \
    && apk del gcc musl-dev make git \
    && rm -rf /tmp/microsocks
CMD ["microsocks", "-p", "8889"]
