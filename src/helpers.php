<?php

declare(strict_types=1);

//字符串-或者_后首字母大写转换
if (! function_exists('str_underline_upper')) {
    //字符串-或者_后首字母大写转换
    function str_underline_upper($value){
        $value = ucwords(str_replace(['_', '-'], ' ', $value));
        return str_replace(' ', '', $value);
    }
}