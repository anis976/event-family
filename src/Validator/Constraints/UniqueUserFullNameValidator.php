<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class UniqueUserFullNameValidator extends ConstraintValidator
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueUserFullName) {
            throw new UnexpectedTypeException($constraint, UniqueUserFullName::class);
        }

        if (!$value instanceof User || null === $value->getId()) {
            return;
        }

        $existing = $this->userRepository->findOneByFullNameForAnotherUser(
            $value->getFirstName(),
            $value->getLastName(),
            $value->getId(),
        );

        if (null !== $existing) {
            $this->context->buildViolation($constraint->message)
                ->atPath('lastName')
                ->addViolation();
        }
    }
}
