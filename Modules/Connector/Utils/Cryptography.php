<?php

namespace Modules\Connector\Utils;

class Cryptography
{
    private const C1 = 52845;
    private const C2 = 22719;
    private const CODES64 = '0A1B2C3D4E5F6G7H89IjKlMnOPqRsTuVWXyZabcdefghijkLmNopQrStUvwxYz+/';

    private static function decode(string $s): string {
        $map = array(
            'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5, 'G' => 6, 'H' => 7,
            'I' => 8, 'J' => 9, 'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15,
            'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19, 'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23,
            'Y' => 24, 'Z' => 25, 'a' => 26, 'b' => 27, 'c' => 28, 'd' => 29, 'e' => 30, 'f' => 31,
            'g' => 32, 'h' => 33, 'i' => 34, 'j' => 35, 'k' => 36, 'l' => 37, 'm' => 38, 'n' => 39,
            'o' => 40, 'p' => 41, 'q' => 42, 'r' => 43, 's' => 44, 't' => 45, 'u' => 46, 'v' => 47,
            'w' => 48, 'x' => 49, 'y' => 50, 'z' => 51, '0' => 52, '1' => 53, '2' => 54, '3' => 55,
            '4' => 56, '5' => 57, '6' => 58, '7' => 59, '8' => 60, '9' => 61, '+' => 62, '/' => 63
        );
    
        $length = strlen($s);
        $result = '';
    
        switch ($length) {
            case 2:
                $i = $map[$s[0]] + ($map[$s[1]] << 6);
                $result = chr($i & 0xFF);
                break;
    
            case 3:
                $i = $map[$s[0]] + ($map[$s[1]] << 6) + ($map[$s[2]] << 12);
                $result = chr($i & 0xFF) . chr(($i >> 8) & 0xFF);
                break;
    
            case 4:
                $i = $map[$s[0]] + ($map[$s[1]] << 6) + ($map[$s[2]] << 12) + ($map[$s[3]] << 18);
                $result = chr($i & 0xFF) . chr(($i >> 8) & 0xFF) . chr(($i >> 16) & 0xFF);
                break;
        }
    
        return $result;
    }
    

    private static function encode(string $s): string {
        $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
        $i = unpack('V', str_pad($s, 4, "\0", STR_PAD_RIGHT))[1];
        $length = strlen($s);
        $result = '';
    
        switch ($length) {
            case 1:
                $result = $map[$i % 64] . $map[($i >> 6) % 64];
                break;
    
            case 2:
                $result = $map[$i % 64] . $map[($i >> 6) % 64] . $map[($i >> 12) % 64];
                break;
    
            case 3:
                $result = $map[$i % 64] . $map[($i >> 6) % 64] . $map[($i >> 12) % 64] . $map[($i >> 18) % 64];
                break;
        }
    
        return $result;
    }
    

    private static function internalDecrypt(string $s, int $key): string {
        $result = $s;
        $seed = $key;

        for ($i = 0; $i < strlen($result); $i++) {
            $result[$i] = chr(ord($result[$i]) ^ ($seed >> 8));
            $seed = ((ord($s[$i]) + $seed) * self::C1 + self::C2) & 0xFFFF;
        }

        return $result;
    }

    private static function preProcess(string $s): string {
        $result = '';
        $length = strlen($s);

        for ($i = 0; $i < $length; $i += 4) {
            $result .= self::decode(substr($s, $i, 4));
        }

        return $result;
    }

    public static function decrypt(string $s, int $key): string {
        return self::internalDecrypt(self::preProcess($s), $key);
    }

    private static function postProcess(string $s): string {
        $result = '';
        $length = strlen($s);

        for ($i = 0; $i < $length; $i += 3) {
            $result .= self::encode(substr($s, $i, 3));
        }

        return $result;
    }

    private static function internalEncrypt(string $s, int $key): string {
        $result = $s;
        $seed = $key;

        for ($i = 0; $i < strlen($result); $i++) {
            $result[$i] = chr(ord($result[$i]) ^ ($seed >> 8));
            $seed = ((ord($result[$i]) + $seed) * self::C1 + self::C2) & 0xFFFF;
        }

        return $result;
    }

    public static function encrypt(string $s, int $key): string {
        return self::postProcess(self::internalEncrypt($s, $key));
    }
}
