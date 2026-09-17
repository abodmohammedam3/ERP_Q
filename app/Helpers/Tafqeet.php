<?php

namespace App\Helpers;

class Tafqeet
{
    /**
     * تحويل الرقم إلى كلمات عربية
     */
    public static function numberToWords(float $number, string $currency = ''): string
    {
        $number = round($number, 2);
        $integerPart = (int) $number;
        $decimalPart = (int) round(($number - $integerPart) * 100);

        if ($integerPart === 0 && $decimalPart === 0) {
            return 'صفر' . ($currency ? ' ' . $currency : '') . ' فقط لا غير';
        }

        $words = self::convertInteger($integerPart);

        if ($decimalPart > 0) {
            $words .= ' و' . self::convertInteger($decimalPart) . ' هللة';
        }

        if ($currency) {
            $words .= ' ' . $currency;
        }

        $words .= ' فقط لا غير';

        return $words;
    }

    private static function convertInteger(int $number): string
    {
        if ($number === 0) return '';

        $ones = [
            '', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة',
            'ستة', 'سبعة', 'ثمانية', 'تسعة', 'عشرة',
            'أحد عشر', 'اثنا عشر', 'ثلاثة عشر', 'أربعة عشر', 'خمسة عشر',
            'ستة عشر', 'سبعة عشر', 'ثمانية عشر', 'تسعة عشر',
        ];

        $tens = [
            '', '', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون',
            'ستون', 'سبعون', 'ثمانون', 'تسعون',
        ];

        $hundreds = [
            '', 'مائة', 'مائتان', 'ثلاثمائة', 'أربعمائة',
            'خمسمائة', 'ستمائة', 'سبعمائة', 'ثمانمائة', 'تسعمائة',
        ];

        if ($number < 20) {
            return $ones[$number];
        }

        if ($number < 100) {
            $unit = $number % 10;
            $ten = intdiv($number, 10);
            if ($unit === 0) return $tens[$ten];
            return $ones[$unit] . ' و' . $tens[$ten];
        }

        if ($number < 1000) {
            $hundred = intdiv($number, 100);
            $remainder = $number % 100;
            $result = $hundreds[$hundred];
            if ($remainder > 0) {
                $result .= ' و' . self::convertInteger($remainder);
            }
            return $result;
        }

        if ($number < 1000000) {
            $thousands = intdiv($number, 1000);
            $remainder = $number % 1000;

            if ($thousands === 1) {
                $result = 'ألف';
            } elseif ($thousands === 2) {
                $result = 'ألفان';
            } elseif ($thousands <= 10) {
                $result = self::convertInteger($thousands) . ' آلاف';
            } else {
                $result = self::convertInteger($thousands) . ' ألف';
            }

            if ($remainder > 0) {
                $result .= ' و' . self::convertInteger($remainder);
            }
            return $result;
        }

        if ($number < 1000000000) {
            $millions = intdiv($number, 1000000);
            $remainder = $number % 1000000;

            if ($millions === 1) {
                $result = 'مليون';
            } elseif ($millions === 2) {
                $result = 'مليونان';
            } elseif ($millions <= 10) {
                $result = self::convertInteger($millions) . ' ملايين';
            } else {
                $result = self::convertInteger($millions) . ' مليون';
            }

            if ($remainder > 0) {
                $result .= ' و' . self::convertInteger($remainder);
            }
            return $result;
        }

        $billions = intdiv($number, 1000000000);
        $remainder = $number % 1000000000;

        if ($billions === 1) {
            $result = 'مليار';
        } elseif ($billions === 2) {
            $result = 'ملياران';
        } elseif ($billions <= 10) {
            $result = self::convertInteger($billions) . ' مليارات';
        } else {
            $result = self::convertInteger($billions) . ' مليار';
        }

        if ($remainder > 0) {
            $result .= ' و' . self::convertInteger($remainder);
        }

        return $result;
    }
}