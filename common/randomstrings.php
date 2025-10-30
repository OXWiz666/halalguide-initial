<?php
$permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
$specialcasesCHAR = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ@#$%!';
$lowercaseCHAR = 'abcdefghijklmnopqrstuvwxyz';
$uppercaseCHAR = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
$numbercaseCHAR = '0123456789';
$alphanumericSmallCHAR = '0123456789abcdefghijklmnopqrstuvwxyz';
$alphanumericBigCHAR = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

if (!function_exists('generate_string')) {
    function generate_string($input, $strength = 16) {
        // Use default character set if input is null, empty, or not a string
        if (empty($input) || !is_string($input)) {
            global $specialcasesCHAR;
            $input = isset($specialcasesCHAR) && !empty($specialcasesCHAR) 
                ? $specialcasesCHAR 
                : '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ@#$%!';
        }
        
        $input_length = strlen($input);
        
        // Safety check: ensure input_length > 0
        if ($input_length <= 0) {
            $input = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $input_length = strlen($input);
        }
        
        $random_string = '';
        for($i = 0; $i < $strength; $i++) {
            $random_character = $input[mt_rand(0, $input_length - 1)];
            $random_string .= $random_character;
        }
        return $random_string;
    }
}

?>