<?php
/*
 * Perform this when onclick() button
 */
$in = \filter_input(\INPUT_GET,'in');
file_put_contents('timer.txt', $in."\r\n", FILE_APPEND);
