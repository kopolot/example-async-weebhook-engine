#syntax=docker/dockerfile:1

FROM php:8.4-cli-alpine

WORKDIR /app
COPY public /app/public

ENV RECEIVER_STORAGE=/tmp/webhook-received.json

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
