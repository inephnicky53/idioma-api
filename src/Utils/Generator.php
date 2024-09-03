<?php

namespace App\Utils;

class Generator
{
    const COMPLEXITY_NUMERIC = 1;
    const COMPLEXITY_ALPHABETIC = 2;
    const COMPLEXITY_SYMBOL = 3;
    const COMPLEXITY_ALPHA_NUMERIC = 4;
    const COMPLEXITY_STRONG = 5;

    public static function generate($n = 8, $complexity = self::COMPLEXITY_NUMERIC): string
    {
        $generator = "";
        $alphabetic = "azertyuiopqsdfghjklmwxcvbn";
        $numeric = "135792468";
        $symboles = "&$*(!-_#?/%";

        if ($complexity === self::COMPLEXITY_NUMERIC)
            $generator .= $numeric;

        if ($complexity === self::COMPLEXITY_ALPHABETIC)
            $generator .= $alphabetic;

        if ($complexity === self::COMPLEXITY_SYMBOL)
            $generator .= $symboles;

        if ($complexity === self::COMPLEXITY_ALPHA_NUMERIC) {
            $generator .= $alphabetic;
            $generator .= strtoupper($alphabetic);
            $generator .= $numeric;
        }
        
        if ($complexity === self::COMPLEXITY_STRONG) {
            $generator .= $alphabetic;
            $generator .= strtoupper($alphabetic);
            $generator .= $numeric;
            $generator .= $symboles;
        }

        $result = "";

        for ($i = 1; $i <= $n; $i++)
            $result .= $generator[(mt_rand() % (strlen($generator)))];

        return $result;
    }
}
