<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidEmailDomainValidator extends ConstraintValidator
{
    /**
     * Known freemail providers that always have valid MX — skip DNS lookup for them
     * to avoid false negatives on slow connections.
     */
    private const TRUSTED_DOMAINS = [
        'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'live.com',
        'icloud.com', 'protonmail.com', 'me.com', 'msn.com',
        // Tunisian providers
        'topnet.tn', 'gnet.tn', 'hexabyte.tn', 'planet.tn', 'tunet.tn',
    ];

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidEmailDomain) {
            throw new UnexpectedTypeException($constraint, ValidEmailDomain::class);
        }

        if (null === $value || '' === $value) {
            return; // handled by NotBlank
        }

        // Basic format guard — must contain exactly one @
        $atPos = strrpos((string) $value, '@');
        if ($atPos === false) {
            return; // format already caught by Email constraint
        }

        $domain = strtolower(substr((string) $value, $atPos + 1));

        if (empty($domain)) {
            return;
        }

        // Trusted providers: no DNS lookup needed
        if (in_array($domain, self::TRUSTED_DOMAINS, true)) {
            return;
        }

        // Check for MX record first, fall back to A record (some small servers only have A)
        $hasMx = @checkdnsrr($domain, 'MX');
        $hasA  = $hasMx ? true : @checkdnsrr($domain, 'A');

        if (!$hasMx && !$hasA) {
            $this->context->buildViolation($constraint->noMxMessage)
                ->setParameter('{{ domain }}', $domain)
                ->setCause($domain)
                ->addViolation();
        }
    }
}
