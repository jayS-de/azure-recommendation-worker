#!/bin/bash

rm -r src/cache
rm iron_test.zip
zip -r iron_test.zip .
iron worker upload --zip iron_test.zip --name start_build --config-file config.json iron/php php src/start_build.php
iron worker upload --zip iron_test.zip --name activate_build --config-file config.json iron/php php src/activate_build.php
iron worker upload --zip iron_test.zip --name order_full_sync --config-file config.json iron/php php src/order_full_sync.php
iron worker upload --zip iron_test.zip --name order_message_sync --config-file config.json iron/php php src/order_message_sync.php
iron worker upload --zip iron_test.zip --name product_full_sync --config-file config.json iron/php php src/product_full_sync.php
