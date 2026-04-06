<?php

$db = mysqli_connect(hostname: $config['db']['host'], username: $config['db']['username'], password: $config['db']['password'], database: $config['db']['name']);
if (!$db) {
    throw new Exception(mysqli_connect_error());
}
