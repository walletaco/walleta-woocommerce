<?php
if (!defined('ABSPATH')) {
    exit;
}

class Walleta_Validation
{
    /**
     * @param string $mobile
     * @return bool
     */
    public static function mobile($mobile)
    {
        return (bool)preg_match('/^09\d{9}$/', $mobile);
    }

    /**
     * @param string $nationalCode
     * @return bool
     */
    public static function nationalCode($nationalCode)
    {
        $pattern = '/^\d{10}$/';
        if (!preg_match($pattern, $nationalCode)) {
            return false;
        }
        $sum = 0;
        $equivalent = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int)$nationalCode[$i] * (10 - $i);
            if ($nationalCode[9] === $nationalCode[$i]) {
                $equivalent++;
            }
        }
        if ($equivalent === 9) {
            return false;
        }
        $remaining = $sum % 11;
        if ($remaining >= 2) {
            $remaining = 11 - $remaining;
        }

        return (bool)($remaining === (int)$nationalCode[9]);
    }
}