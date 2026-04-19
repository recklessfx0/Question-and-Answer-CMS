<?php
set_exception_handler(function ($e) {
    error_log($e);
    http_response_code(500);
    echo "Internal Server Error";
});
