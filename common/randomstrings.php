<?php
$permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
$specialcasesCHAR = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ@#$%!';
$lowercaseCHAR = 'abcdefghijklmnopqrstuvwxyz';
$uppercaseCHAR = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
$numbercaseCHAR = '0123456789';
$alphanumericSmallCHAR = '0123456789abcdefghijklmnopqrstuvwxyz';
$alphanumericBigCHAR = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

function generate_string($input, $strength = 16) {
    $input_length = strlen($input);
    $random_string = '';
    for($i = 0; $i < $strength; $i++) {
        $random_character = $input[mt_rand(0, $input_length - 1)];
        $random_string .= $random_character;
    }
    return $random_string;
}

?>