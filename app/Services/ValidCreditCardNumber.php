<?php

namespace App\Services;

class ValidCreditCardNumber
{
    /**
     * Validate if the credit card number contains three consecutive same digits.
     *
     * @param string $number
     * @return bool
     */
    public function isValid($number)
    {
        $number = str_replace('-', '', $number);
        $number = str_replace(' ', '', $number);

        // check if the credit card number matches the pattern
        if (preg_match('/(\d)\1{2}/', $number)) {
            return true;
        }
        return false;
    }
}
