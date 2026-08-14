<?php

if (! function_exists('shortNumber')) {
    /**
     * Format angka jadi singkat (contoh: 1200 -> 1.2k)
     */
    function shortNumber($num)
    {
        if ($num >= 1000000000) {
            return number_format($num / 1000000000, 1).'B';
        } elseif ($num >= 1000000) {
            return number_format($num / 1000000, 1).'M';
        } elseif ($num >= 1000) {
            return number_format($num / 1000, 1).'k';
        }

        return $num;
    }
}
