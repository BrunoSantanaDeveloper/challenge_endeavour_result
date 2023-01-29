<?php

namespace App\Services;

class ValidAge
{
    /**
     * Validate if the age is between 18 and 65 (or unknown).
     *
     * @param int|null $age
     * @return bool
     */
    public function isValid($age)
    {
        // if age is unknown, return true
        if (is_null($age)) {
            return true;
        }
        // check if age is between 18 and 65
        return $age >= 18 && $age <= 65;
    }
}
