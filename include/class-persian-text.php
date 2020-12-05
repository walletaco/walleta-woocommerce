<?php
if (!defined('ABSPATH')) {
    exit;
}

class Walleta_Persian_Text
{
    /**
     * @var string[]
     */
    protected static $enNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    /**
     * @var string[]
     */
    protected static $arNumbers = ['۰', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    /**
     * @var string[]
     */
    protected static $faNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    /**
     * @param mixed $string
     * @return string|array
     */
    public static function toEnglishNumber($string)
    {
        $string = static::convert(self::$faNumbers, self::$enNumbers, $string);

        return static::convert(self::$arNumbers, self::$enNumbers, $string);
    }

    /**
     * @param array $search
     * @param array $replace
     * @param mixed $subject
     * @return mixed
     */
    protected static function convert(array $search, array $replace, $subject)
    {
        if (trim($subject) === '' || is_bool($subject)) {
            return $subject;
        }

        return str_replace($search, $replace, $subject);
    }
}
