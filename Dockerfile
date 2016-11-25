FROM iron/php

WORKDIR /app
ADD . /app

CMD php bin/console.php $CMD
