<?php

    function byteLength($string)
    {
      return mb_strlen((string)$string, '8bit');
    }

    function compareString($expected, $actual)
    {
        if (!is_string($expected)) {
            echo 'Expected expected value to be a string, ' . gettype($expected) . ' given.';
        }

        if (!is_string($actual)) {
            echo 'Expected actual value to be a string, ' . gettype($actual) . ' given.';
        }

        if (function_exists('hash_equals')) {
            return hash_equals($expected, $actual);
        }

        $expected .= "\0";
        $actual .= "\0";
        $expectedLength = byteLength($expected);
        $actualLength = byteLength($actual);
        $diff = $expectedLength - $actualLength;
        for ($i = 0; $i < $actualLength; $i++) {
            $diff |= (ord($actual[$i]) ^ ord($expected[$i % $expectedLength]));
        }

        return $diff === 0;
    }

    function validatePassword($password, $hash)
    {
        if (!is_string($password) || $password === '') {
            echo 'Password must be a string and cannot be empty.';
        }

        if (!preg_match('/^\$2[axy]\$(\d\d)\$[\.\/0-9A-Za-z]{22}/', $hash, $matches)
            || $matches[1] < 4
            || $matches[1] > 30
        ) {
            echo 'Hash is invalid.';
        }

        if (function_exists('password_verify')) {
            return password_verify($password, $hash);
        }

        $test = crypt($password, $hash);
        $n = strlen($test);
        if ($n !== 60) {
            return false;
        }

        return compareString($test, $hash);
    }
