<?php
$json = file_get_contents(dirname(__FILE__) . '/footprint.data.json');
$footprint = json_decode($json, TRUE);
echo '<pre>';
print_r($footprint);
echo '</pre>';
