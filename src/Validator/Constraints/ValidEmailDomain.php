<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Validates that an email address has a real domain with MX or A records.
 * Use alongside Symfony's built-in Email constraint for format checking.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class ValidEmailDomain extends Constraint
{
    public string $message = 'The domain "{{ domain }}" does not accept emails. Please use a real, working email address.';
    public string $noMxMessage = 'The domain "{{ domain }}" has no mail server configured. Please use a valid email address.';
}
